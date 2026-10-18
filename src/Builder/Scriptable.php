<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder;

use RuntimeException;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchAssetIndex;

/**
 * Common script/variable/list/sound/comment operations shared by {@see Sprite}
 * and {@see Stage}.
 *
 * Both target kinds wrap a live target entry from the project's `targets`
 * array by reference, so mutations write straight through. Sprite-only
 * setters (position, size, rotation style, …) and stage-only setters
 * (backdrop) live on the subclasses.
 */
abstract class Scriptable
{
    /** WAV format tags whose sample count is `data` length / block align: PCM, IEEE float, extensible. */
    private const UNCOMPRESSED_WAV_FORMATS = [0x0001, 0x0003, 0xFFFE];

    /**
     * @var array<string, mixed>
     */
    protected array $target;

    /**
     * @param array<string, mixed> $target the live target (by reference)
     */
    public function __construct(array &$target, protected readonly ScratchAssetIndex $assets)
    {
        $this->target = &$target;
    }

    /**
     * The target's display name (matches `target.name` in the `.sb3`).
     */
    public function name(): string
    {
        return $this->target['name'];
    }

    /**
     * Return the current target data.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->target;
    }

    /**
     * Add a script (a block chain starting at a hat or root block) to this
     * target. Resolves id collisions with existing blocks automatically.
     */
    public function addScript(Block $root, int|float $x = 0, int|float $y = 0): static
    {
        $newBlocks = $root->toBlocks($x, $y);

        $collisions = array_keys(array_intersect_key($newBlocks, $this->target['blocks']));
        if ($collisions !== []) {
            $idMap = [];
            foreach ($collisions as $oldId) {
                do {
                    $newId = Id::generate();
                } while (isset($this->target['blocks'][$newId]) || isset($newBlocks[$newId]) || \in_array($newId, $idMap, true));
                $idMap[$oldId] = $newId;
            }
            $newBlocks = $this->remapBlockIds($newBlocks, $idMap);
        }

        foreach ($newBlocks as $id => $block) {
            $this->target['blocks'][$id] = $block;
        }

        return $this;
    }

    /**
     * Find all non-null blocks with the given opcode.
     *
     * @return list<BlockRef>
     */
    public function findBlocks(string $opcode): array
    {
        $result = [];
        foreach ($this->target['blocks'] as $id => $block) {
            if (\is_array($block) && ($block['opcode'] ?? null) === $opcode) {
                $result[] = new BlockRef($this->target['blocks'], (string) $id);
            }
        }

        return $result;
    }

    /**
     * Find top-level scripts (hat blocks at the root of a chain).
     *
     * `$filter` may be:
     *   - null         — every top-level script
     *   - string       — only scripts whose hat opcode equals the string
     *   - list<string> — only scripts whose hat opcode appears in the list
     *   - callable(BlockRef): bool — predicate over the hat's BlockRef
     *
     * @param null|string|list<string>|callable(BlockRef): bool $filter
     *
     * @return list<BlockRef>
     */
    public function findScripts(null|string|array|callable $filter = null): array
    {
        $result = [];
        foreach ($this->target['blocks'] as $id => $block) {
            if (!\is_array($block) || empty($block['topLevel'])) {
                continue;
            }
            $opcode = \is_string($block['opcode'] ?? null) ? $block['opcode'] : '';
            $ref = new BlockRef($this->target['blocks'], (string) $id);
            if ($this->matchesFilter($ref, $opcode, $filter)) {
                $result[] = $ref;
            }
        }

        return $result;
    }

    /**
     * Get a reference to a specific block by id.
     *
     * @throws RuntimeException if no block with the given id exists on this target
     */
    public function block(string $id): BlockRef
    {
        $block = $this->target['blocks'][$id] ?? null;
        if (!\is_array($block)) {
            throw new RuntimeException(\sprintf('Block "%s" not found on target "%s"', $id, $this->target['name']));
        }

        return new BlockRef($this->target['blocks'], $id);
    }

    /**
     * Add a variable to this target and return a {@see VariableRef}.
     */
    public function addVariable(string $name, string|int|float|bool $initialValue = ''): VariableRef
    {
        $id = Id::generate();
        $this->target['variables'][$id] = [$name, $initialValue];

        return new VariableRef($name, $id);
    }

    /**
     * Add a list to this target and return a {@see ListRef}.
     */
    public function addList(string $name): ListRef
    {
        $id = Id::generate();
        $this->target['lists'][$id] = [$name, []];

        return new ListRef($name, $id);
    }

    /**
     * Add a sound from raw audio data and return its index.
     *
     * `$rate` and `$sampleCount` are auto-detected from the WAV header when
     * `$dataFormat` is `wav` and the arguments are left null. For other
     * formats supply the values explicitly (defaults: 44100 Hz, 0 samples —
     * the latter is a safe placeholder when the count isn't known).
     * A compressed WAV (e.g. IMA ADPCM) without a `fact` chunk has no
     * derivable sample count; pass `$sampleCount` for it.
     *
     * @throws \InvalidArgumentException if the WAV sample count can't be determined
     */
    public function addSound(
        string $name,
        string $data,
        string $dataFormat = 'wav',
        ?int $rate = null,
        ?int $sampleCount = null,
    ): int {
        $assetId = hash('md5', $data);
        $md5ext = "{$assetId}.{$dataFormat}";
        $this->assets->add($md5ext, $data);

        if (($rate === null || $sampleCount === null) && $dataFormat === 'wav') {
            $parsed = self::parseWavHeader($data);
            $rate ??= $parsed['rate'] ?? null;
            $sampleCount ??= $parsed['sampleCount'] ?? null;
            $formatTag = $parsed['formatTag'] ?? null;
            if ($sampleCount === null && $formatTag !== null && !\in_array($formatTag, self::UNCOMPRESSED_WAV_FORMATS, true)) {
                // Compressed (e.g. IMA ADPCM) without a `fact` chunk: the sample count
                // can't be derived from the byte length, so make the caller supply it.
                throw new \InvalidArgumentException(\sprintf(
                    'Cannot determine the sample count of WAV sound "%s" (format tag 0x%x, no fact chunk); pass $sampleCount explicitly',
                    $name,
                    $formatTag,
                ));
            }
        }

        $this->target['sounds'][] = [
            'name' => $name,
            'dataFormat' => $dataFormat,
            'assetId' => $assetId,
            'md5ext' => $md5ext,
            'rate' => $rate ?? 44100,
            'sampleCount' => $sampleCount ?? 0,
        ];

        return \count($this->target['sounds']) - 1;
    }

    /**
     * Add a comment to this target. Pass `$blockId` to anchor it to a specific
     * block; leave null for a free-floating comment.
     */
    public function addComment(
        string $text,
        int|float $x = 0,
        int|float $y = 0,
        int|float $width = 200,
        int|float $height = 200,
        bool $minimized = false,
        ?string $blockId = null,
    ): CommentRef {
        $id = Id::generate();
        $this->target['comments'] ??= [];
        $this->target['comments'][$id] = [
            'blockId' => $blockId,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'minimized' => $minimized,
            'text' => $text,
        ];

        return new CommentRef($this->target['comments'], $id);
    }

    /**
     * Find comments on this target, optionally filtered by anchor block id.
     *
     * @return list<CommentRef>
     */
    public function findComments(?string $blockId = null): array
    {
        $result = [];
        foreach ($this->target['comments'] ?? [] as $id => $comment) {
            if (!\is_array($comment)) {
                continue;
            }
            if ($blockId !== null && ($comment['blockId'] ?? null) !== $blockId) {
                continue;
            }
            $result[] = new CommentRef($this->target['comments'], (string) $id);
        }

        return $result;
    }

    /**
     * @return array{rate?: int, sampleCount?: int, formatTag?: int}|null
     */
    private static function parseWavHeader(string $data): ?array
    {
        if (\strlen($data) < 44) {
            return null;
        }
        if (substr($data, 0, 4) !== 'RIFF' || substr($data, 8, 4) !== 'WAVE') {
            return null;
        }

        // Walk chunks looking for `fmt ` (format, sample rate, frame size), `fact`
        // (sample count of compressed formats) and `data` (length).
        $formatTag = null;
        $rate = null;
        $blockAlign = null;
        $factSampleCount = null;
        $dataBytes = null;
        $cursor = 12;
        $length = \strlen($data);
        while ($cursor + 8 <= $length) {
            $chunkId = substr($data, $cursor, 4);
            $chunkSize = unpack('V', substr($data, $cursor + 4, 4))[1] ?? 0;
            $payload = substr($data, $cursor + 8, $chunkSize);
            if ($chunkId === 'fmt ' && \strlen($payload) >= 16) {
                $fmt = unpack('vformatTag/vchannels/Vrate/VbyteRate/vblockAlign', $payload);
                $formatTag = $fmt['formatTag'];
                $rate = $fmt['rate'];
                $blockAlign = $fmt['blockAlign'];
            } elseif ($chunkId === 'fact' && \strlen($payload) >= 4) {
                $factSampleCount = unpack('V', $payload)[1];
            } elseif ($chunkId === 'data') {
                $dataBytes = $chunkSize;
            }
            $cursor += 8 + $chunkSize + ($chunkSize % 2);
        }

        $out = [];
        if (\is_int($rate) && $rate > 0) {
            $out['rate'] = $rate;
        }
        if (\is_int($factSampleCount)) {
            $out['sampleCount'] = $factSampleCount;
        } elseif (\is_int($dataBytes) && \in_array($formatTag, self::UNCOMPRESSED_WAV_FORMATS, true)) {
            if (\is_int($blockAlign) && $blockAlign > 0) {
                $out['sampleCount'] = intdiv($dataBytes, $blockAlign);
            }
        }
        if (\is_int($formatTag)) {
            $out['formatTag'] = $formatTag;
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param null|string|list<string>|callable(BlockRef): bool $filter
     */
    private function matchesFilter(BlockRef $ref, string $opcode, null|string|array|callable $filter): bool
    {
        if ($filter === null) {
            return true;
        }
        if (\is_string($filter)) {
            return $opcode === $filter;
        }
        if (\is_array($filter)) {
            return \in_array($opcode, $filter, true);
        }

        return (bool) $filter($ref);
    }

    /**
     * @param array<string, mixed> $blocks
     * @param array<string, string> $idMap
     *
     * @return array<string, mixed>
     */
    private function remapBlockIds(array $blocks, array $idMap): array
    {
        $result = [];
        foreach ($blocks as $oldId => $block) {
            if (!\is_array($block)) {
                $result[$oldId] = $block;

                continue;
            }
            $newId = $idMap[$oldId] ?? $oldId;

            if (isset($block['next']) && isset($idMap[$block['next']])) {
                $block['next'] = $idMap[$block['next']];
            }
            if (isset($block['parent']) && isset($idMap[$block['parent']])) {
                $block['parent'] = $idMap[$block['parent']];
            }
            foreach (($block['inputs'] ?? []) as $name => $input) {
                if (!\is_array($input)) {
                    continue;
                }
                if (isset($input[1]) && \is_string($input[1]) && isset($idMap[$input[1]])) {
                    $input[1] = $idMap[$input[1]];
                }
                if (isset($input[2]) && \is_string($input[2]) && isset($idMap[$input[2]])) {
                    $input[2] = $idMap[$input[2]];
                }
                $block['inputs'][$name] = $input;
            }

            $result[$newId] = $block;
        }

        return $result;
    }
}
