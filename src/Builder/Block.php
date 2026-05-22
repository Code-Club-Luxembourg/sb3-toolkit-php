<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder;

/**
 * A fluent builder for a single Scratch block and its chained successors.
 *
 * Create with {@see Block::make()} (or a hat shortcut), configure with the
 * fluent {@see input()}, {@see field()} and {@see mutation()} methods, and
 * append successors with {@see then()}.
 */
final class Block
{
    public readonly string $id;

    /**
     * @var array<string, Input>
     */
    private array $inputs = [];

    /**
     * @var array<string, array{0: string|int|float, 1: string|null}>
     */
    private array $fields = [];

    private ?Block $next = null;

    private ?string $parentId = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $mutation = null;

    private bool $shadow = false;

    private function __construct(private readonly string $opcode)
    {
        $this->id = Id::generate();
    }

    /**
     * Mark this block as a shadow. Used internally to flag dropdown menu
     * blocks; user code rarely needs to call this.
     *
     * @internal
     */
    public function asShadow(): self
    {
        $this->shadow = true;

        return $this;
    }

    /**
     * Create a block for the given opcode.
     */
    public static function make(string $opcode): self
    {
        return new self($opcode);
    }

    /**
     * Chain a list of blocks via {@see then()} and return the head, or null when
     * the list is empty. Used to turn a variadic body into a single substack.
     */
    public static function stack(self ...$blocks): ?self
    {
        if ($blocks === []) {
            return null;
        }

        $head = $blocks[0];
        for ($i = 1, $n = \count($blocks); $i < $n; ++$i) {
            $head->then($blocks[$i]);
        }

        return $head;
    }

    /**
     * Set an input. Plain int/float/string values and {@see Block} reporters are
     * wrapped automatically; pass an {@see Input} for a specific encoding.
     */
    public function input(string $name, Input|self|int|float|string|null $value): self
    {
        if ($value === null) {
            return $this;
        }
        $this->inputs[$name] = Input::wrap($value);

        return $this;
    }

    /**
     * Set a field. The optional id is used by fields that reference an entity
     * (e.g. a variable's id).
     */
    public function field(string $name, string $value, ?string $id = null): self
    {
        $this->fields[$name] = [$value, $id];

        return $this;
    }

    /**
     * Attach a procedure/control mutation to this block.
     *
     * @param array<string, mixed> $mutation
     */
    public function mutation(array $mutation): self
    {
        $this->mutation = $mutation;

        return $this;
    }

    /**
     * Append a block at the end of this chain and return the root, so
     * `$root->then($a)->then($b)` keeps anchoring on the root. Pair with
     * {@see addScript()} which expects the root.
     */
    public function then(self $block): self
    {
        $tail = $this;
        while ($tail->next instanceof self) {
            $tail = $tail->next;
        }
        $tail->next = $block;
        $block->parentId = $tail->id;

        return $this;
    }

    /**
     * Append a block at the end of this chain and return the appended block, so
     * `$root->andThen($a)->andThen($b)` walks forward (each call returns the
     * newly-added tail). Use this when you need a reference to a deep block;
     * call {@see Sprite::addScript()} with the *original* root.
     */
    public function andThen(self $block): self
    {
        $this->then($block);

        return $block;
    }

    /**
     * The id of this block's predecessor in the chain, populated when the block
     * was appended with {@see then()} / {@see andThen()}. Null for the root.
     */
    public function parentId(): ?string
    {
        return $this->parentId;
    }

    /**
     * Flatten this script into a blocks map.
     *
     * The root block is marked `topLevel = true` at the given coordinates.
     *
     * @return array<string, array<string, mixed>>
     */
    public function toBlocks(int|float $x = 0, int|float $y = 0): array
    {
        $result = [];
        $this->collectInto($result, null, true, $x, $y);

        return $result;
    }

    /**
     * @param array<string, array<string, mixed>> $blocks
     */
    private function collectInto(array &$blocks, ?string $parentId, bool $isTopLevel, int|float $x, int|float $y): void
    {
        $encodedInputs = [];
        foreach ($this->inputs as $name => $input) {
            $encodedInputs[$name] = $input->toSlot();
        }

        $block = [
            'opcode' => $this->opcode,
            'next' => $this->next?->id,
            'parent' => $parentId,
            'inputs' => $encodedInputs,
            'fields' => $this->fields,
            'shadow' => $this->shadow,
            'topLevel' => $isTopLevel,
        ];

        if ($isTopLevel) {
            $block['x'] = $x;
            $block['y'] = $y;
        }

        if ($this->mutation !== null) {
            $block['mutation'] = $this->mutation;
        }

        $blocks[$this->id] = $block;

        foreach ($this->inputs as $input) {
            foreach ($input->childBlocks() as $child) {
                $child->collectInto($blocks, $this->id, false, 0, 0);
            }
        }

        $this->next?->collectInto($blocks, $this->id, false, 0, 0);
    }
}
