<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Scratch;

use Generator;
use JsonException;
use RuntimeException;
use CodeClubLuxembourg\Sb3Toolkit\Builder\BlockRef;
use CodeClubLuxembourg\Sb3Toolkit\Builder\BroadcastRef;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Id;
use CodeClubLuxembourg\Sb3Toolkit\Builder\ListRef;
use CodeClubLuxembourg\Sb3Toolkit\Builder\ScriptLocation;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Sprite;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Stage;
use CodeClubLuxembourg\Sb3Toolkit\Builder\VariableRef;
use CodeClubLuxembourg\Sb3Toolkit\Support\Json;
use ZipArchive;

/**
 * Represents a Scratch project (the parsed `project.json` plus its assets).
 *
 * Project data is held as a nested associative array mirroring the on-disk
 * `project.json`, which preserves any fields not described by the schema so
 * that load/modify/save round-trips faithfully.
 *
 * @phpstan-type ProjectData array<string, mixed>
 */
final class ScratchProject
{
    private const int STAGE_WIDTH = 480;
    private const int STAGE_HEIGHT = 360;

    private const string BLANK_COSTUME_SVG = '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="-0.5 -0.5 1 1"></svg>';

    /**
     * Blocks whose inputs hold absolute stage coordinates, with the metadata
     * needed to offset them when arranging projects in a grid.
     *
     * @var array<string, array{x?: string, y?: string, xArrayAdjust?: int, yArrayAdjust?: int, coerceNumber?: bool}>
     */
    private const array ABSOLUTE_COORD_INPUTS = [
        'motion_gotoxy' => ['x' => 'X', 'y' => 'Y', 'xArrayAdjust' => 100, 'yArrayAdjust' => -50, 'coerceNumber' => true],
        'motion_glideto' => ['x' => 'X', 'y' => 'Y'],
        'motion_glidesecstoxy' => ['x' => 'X', 'y' => 'Y'],
        'motion_setx' => ['x' => 'X'],
        'motion_sety' => ['y' => 'Y'],
    ];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public array $data,
        public ScratchAssetIndex $assets,
    ) {}

    /**
     * Load a `.sb3` file from disk into a `ScratchProject`.
     *
     * @throws RuntimeException if the archive cannot be opened, `project.json`
     *                          is missing, or the JSON is malformed
     */
    public static function fromSb3File(string $filePath): self
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException(\sprintf('Failed to open .sb3 archive: %s', $filePath));
        }

        $projectText = $zip->getFromName('project.json');
        if ($projectText === false) {
            $zip->close();
            throw new RuntimeException(\sprintf('Missing project.json in %s', $filePath));
        }

        $data = self::parseProjectData($projectText);

        $assets = new ScratchAssetIndex();
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = $zip->getNameIndex($i);
            if ($name === false || $name === 'project.json' || str_ends_with($name, '/')) {
                continue;
            }
            $content = $zip->getFromIndex($i);
            if ($content !== false) {
                $assets->add($name, $content);
            }
        }
        $zip->close();

        return new self($data, $assets);
    }

    /**
     * Create a new empty project containing only a blank stage.
     *
     * @param array<string, mixed> $meta override values for `project.json` meta
     */
    public static function create(array $meta = []): self
    {
        $svg = self::BLANK_COSTUME_SVG;
        $md5 = hash('md5', $svg);
        $md5ext = "{$md5}.svg";

        $assets = new ScratchAssetIndex();
        $assets->add($md5ext, $svg);

        $stage = [
            'isStage' => true,
            'name' => 'Stage',
            'variables' => [],
            'lists' => [],
            'broadcasts' => [],
            'blocks' => [],
            'comments' => [],
            'currentCostume' => 0,
            'costumes' => [
                [
                    'name' => 'backdrop1',
                    'dataFormat' => 'svg',
                    'assetId' => $md5,
                    'md5ext' => $md5ext,
                    'rotationCenterX' => 240,
                    'rotationCenterY' => 180,
                ],
            ],
            'sounds' => [],
            'volume' => 100,
            'layerOrder' => 0,
        ];

        $data = [
            'targets' => [$stage],
            'monitors' => [],
            'extensions' => [],
            'meta' => [
                'semver' => '3.0.0',
                'vm' => '0.2.0',
                ...$meta,
            ],
        ];

        return new self($data, $assets);
    }

    /**
     * Add a new sprite with a blank costume. Returns a {@see Sprite} for further
     * work.
     */
    public function addSprite(
        string $name,
        int|float $x = 0,
        int|float $y = 0,
        int|float $size = 100,
        int|float $direction = 90,
        bool $visible = true,
        string $rotationStyle = 'all around',
        bool $draggable = false,
    ): Sprite {
        $layerOrder = \count($this->listSprites()) + 1;

        $svg = self::BLANK_COSTUME_SVG;
        $md5 = hash('md5', $svg);
        $md5ext = "{$md5}.svg";
        if (!$this->assets->has($md5ext)) {
            $this->assets->add($md5ext, $svg);
        }

        $sprite = [
            'isStage' => false,
            'name' => $name,
            'variables' => [],
            'lists' => [],
            'broadcasts' => [],
            'blocks' => [],
            'comments' => [],
            'currentCostume' => 0,
            'costumes' => [
                [
                    'name' => 'costume1',
                    'dataFormat' => 'svg',
                    'assetId' => $md5,
                    'md5ext' => $md5ext,
                    'rotationCenterX' => 0,
                    'rotationCenterY' => 0,
                ],
            ],
            'sounds' => [],
            'volume' => 100,
            'layerOrder' => $layerOrder,
            'visible' => $visible,
            'x' => $x,
            'y' => $y,
            'size' => $size,
            'direction' => $direction,
            'draggable' => $draggable,
            'rotationStyle' => $rotationStyle,
        ];

        $this->data['targets'][] = $sprite;
        $lastKey = array_key_last($this->data['targets']);
        $targetRef = &$this->data['targets'][$lastKey];

        return new Sprite($targetRef, $this->assets);
    }

    /**
     * Get a {@see Sprite} for an existing sprite by name.
     *
     * @throws RuntimeException if no sprite with the given name exists
     */
    public function sprite(string $name): Sprite
    {
        $targetRef = &$this->spriteReference($name);

        return new Sprite($targetRef, $this->assets);
    }

    /**
     * Get a {@see Stage} for adding stage-level scripts, backdrops, variables,
     * and lists. The stage is constructed once per call but mutates the same
     * underlying target as {@see getStage()}.
     *
     * @throws RuntimeException if the project has no stage target
     */
    public function stage(): Stage
    {
        $stageRef = &$this->stageReference();

        return new Stage($stageRef, $this->assets);
    }

    /**
     * Iterate every top-level script across the stage and every sprite,
     * yielding {@see ScriptLocation} tuples of `(target, script)`. `$filter`
     * has the same shape as {@see \CodeClubLuxembourg\Sb3Toolkit\Builder\Scriptable::findScripts()}.
     *
     * @param null|string|list<string>|callable(BlockRef): bool $filter
     *
     * @return Generator<ScriptLocation>
     */
    public function findAllScripts(null|string|array|callable $filter = null): Generator
    {
        $stage = $this->stage();
        foreach ($stage->findScripts($filter) as $script) {
            yield new ScriptLocation($stage, $script);
        }
        foreach ($this->listSprites() as $spriteData) {
            $sprite = $this->sprite($spriteData['name']);
            foreach ($sprite->findScripts($filter) as $script) {
                yield new ScriptLocation($sprite, $script);
            }
        }
    }

    /**
     * Set the active backdrop on the stage by its index.
     */
    public function setCurrentBackdrop(int $index): self
    {
        $stage = &$this->stageReference();
        $count = \count($stage['costumes'] ?? []);
        if ($index < 0 || $index >= $count) {
            throw new RuntimeException(\sprintf(
                'Backdrop index %d is out of range (0..%d)',
                $index,
                $count - 1,
            ));
        }
        $stage['currentCostume'] = $index;

        return $this;
    }

    /**
     * Add a backdrop to the stage from raw image data and return its index.
     */
    public function addBackdrop(string $name, string $data, string $dataFormat = 'svg'): int
    {
        $assetId = hash('md5', $data);
        $md5ext = "{$assetId}.{$dataFormat}";
        $this->assets->add($md5ext, $data);

        $stage = &$this->stageReference();
        $stage['costumes'][] = [
            'name' => $name,
            'dataFormat' => $dataFormat,
            'assetId' => $assetId,
            'md5ext' => $md5ext,
            'rotationCenterX' => 240,
            'rotationCenterY' => 180,
        ];

        return \count($stage['costumes']) - 1;
    }

    /**
     * Add a global (stage-level) variable and return a {@see VariableRef}.
     */
    public function addVariable(string $name, string|int|float|bool $initialValue = ''): VariableRef
    {
        $id = Id::generate();
        $stage = &$this->stageReference();
        $stage['variables'][$id] = [$name, $initialValue];

        return new VariableRef($name, $id);
    }

    /**
     * Add a global (stage-level) list and return a {@see ListRef}.
     */
    public function addList(string $name): ListRef
    {
        $id = Id::generate();
        $stage = &$this->stageReference();
        $stage['lists'][$id] = [$name, []];

        return new ListRef($name, $id);
    }

    /**
     * Add a broadcast message on the stage and return a {@see BroadcastRef}.
     */
    public function addBroadcast(string $name): BroadcastRef
    {
        $id = Id::generate();
        $stage = &$this->stageReference();
        $stage['broadcasts'][$id] = $name;

        return new BroadcastRef($name, $id);
    }

    /**
     * Find an existing broadcast by name, or return null when none exists.
     */
    public function findBroadcast(string $name): ?BroadcastRef
    {
        $broadcasts = $this->stageReference()['broadcasts'] ?? [];
        if (!\is_array($broadcasts)) {
            return null;
        }
        foreach ($broadcasts as $id => $bcastName) {
            if ($bcastName === $name) {
                return new BroadcastRef($name, (string) $id);
            }
        }

        return null;
    }

    /**
     * List all sprite targets in the project.
     *
     * @return list<array<string, mixed>>
     */
    public function listSprites(): array
    {
        $sprites = [];
        foreach ($this->data['targets'] as $target) {
            if (empty($target['isStage'])) {
                $sprites[] = $target;
            }
        }

        return $sprites;
    }

    /**
     * Return the stage target, throwing if none exists.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException if the project has no stage target
     */
    public function getStage(): array
    {
        foreach ($this->data['targets'] as $target) {
            if (!empty($target['isStage'])) {
                return $target;
            }
        }

        throw new RuntimeException('Project has no stage target');
    }

    /**
     * Return a live reference to the stage target for in-place mutation.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException if the project has no stage target
     */
    public function &stageReference(): array
    {
        foreach ($this->data['targets'] as $index => $target) {
            if (!empty($target['isStage'])) {
                return $this->data['targets'][$index];
            }
        }

        throw new RuntimeException('Project has no stage target');
    }

    /**
     * Return a live reference to a sprite target by name for in-place mutation.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException if no sprite with the given name exists
     */
    public function &spriteReference(string $name): array
    {
        foreach ($this->data['targets'] as $index => $target) {
            if (empty($target['isStage']) && ($target['name'] ?? null) === $name) {
                return $this->data['targets'][$index];
            }
        }

        throw new RuntimeException(\sprintf('Sprite "%s" not found in project', $name));
    }

    /**
     * Merge sprites from source projects into a base project.
     *
     * @param list<self>        $sources
     * @param list<string>|null $spriteNames     limit to these sprite names
     * @param null|callable(string, array<string, true>): string $renameStrategy
     */
    public static function mergeSprites(
        self $base,
        array $sources,
        ?array $spriteNames = null,
        ?callable $renameStrategy = null,
    ): self {
        ['data' => $mergedData, 'assets' => $mergedAssets, 'names' => $existingNames] = self::initMergedProject($base);
        $rename = $renameStrategy ?? self::defaultRenameStrategy(...);
        $filterNames = $spriteNames === null ? null : array_fill_keys($spriteNames, true);

        foreach ($sources as $source) {
            foreach ($source->listSprites() as $sprite) {
                if ($filterNames !== null && !isset($filterNames[$sprite['name']])) {
                    continue;
                }

                $cloned = $sprite;
                if (isset($existingNames[$cloned['name']])) {
                    $cloned['name'] = $rename($cloned['name'], $existingNames);
                }
                $existingNames[$cloned['name']] = true;

                $mergedData['targets'][] = $cloned;
                self::collectAssetsFromTarget($cloned, $source, $mergedAssets);
            }
        }

        self::normalizeLayerOrder($mergedData);

        return new self($mergedData, $mergedAssets);
    }

    /**
     * Combine projects by placing each project into a grid cell.
     *
     * @param list<self> $projects
     */
    public static function gridProjects(array $projects, int $columns, ?int $rows = null): self
    {
        if ($projects === []) {
            throw new RuntimeException('At least one project is required');
        }
        if ($columns <= 0) {
            throw new RuntimeException('Grid columns must be a positive number');
        }

        $rowCount = $rows ?? (int) ceil(\count($projects) / $columns);
        if ($rowCount <= 0) {
            throw new RuntimeException('Grid rows must be a positive number');
        }
        if ($rowCount * $columns < \count($projects)) {
            throw new RuntimeException('Grid is too small for the number of projects');
        }

        $base = $projects[0];
        ['data' => $mergedData, 'assets' => $mergedAssets, 'names' => $existingNames] = self::initMergedProject($base);

        self::applyGridOffset($mergedData, self::gridOffset(0, $columns, $rowCount));

        $count = \count($projects);
        for ($index = 1; $index < $count; ++$index) {
            $source = $projects[$index];
            $offset = self::gridOffset($index, $columns, $rowCount);

            foreach ($source->listSprites() as $sprite) {
                $cloned = $sprite;
                if (isset($existingNames[$cloned['name']])) {
                    $cloned['name'] = self::defaultRenameStrategy($cloned['name'], $existingNames);
                }
                $existingNames[$cloned['name']] = true;
                self::applyOffsetToTarget($cloned, $offset['x'], $offset['y']);
                $mergedData['targets'][] = $cloned;
                self::collectAssetsFromTarget($cloned, $source, $mergedAssets);
            }
        }

        self::normalizeLayerOrder($mergedData);

        return new self($mergedData, $mergedAssets);
    }

    /**
     * Compute the total pixel dimensions for a project grid.
     *
     * @return array{width: int, height: int}
     */
    public static function gridPixelSize(int $columns, int $rows): array
    {
        return [
            'width' => $columns * self::STAGE_WIDTH,
            'height' => $rows * self::STAGE_HEIGHT,
        ];
    }

    /**
     * Serialize the project and assets into a `.sb3` file.
     *
     * @throws RuntimeException if the archive cannot be created at the target path
     */
    public function saveToSb3File(string $filePath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(\sprintf('Failed to create .sb3 archive: %s', $filePath));
        }

        $zip->addFromString('project.json', Json::encodeProjectData($this->data));
        foreach ($this->assets->entries() as $md5ext => $data) {
            $zip->addFromString($md5ext, $data);
        }

        $zip->close();
    }

    /**
     * Run an optimizer over every asset and propagate the resulting hash
     * changes through `project.json`.
     *
     * The callback receives the current md5ext and bytes; whatever it returns
     * becomes the new bytes. When the bytes change, the asset's md5 is
     * recomputed, the file extension is preserved, the asset index is
     * rewritten under the new md5ext, and every costume/sound on every target
     * that referenced the old md5ext is updated (both `md5ext` and `assetId`).
     * Returns a `[old => new]` map of the md5ext keys that changed (empty
     * when every asset was returned unchanged).
     *
     * Optimizers MUST preserve the file extension. Changing the format
     * (svg → png) is a conversion, not an optimization, and is out of scope.
     *
     * @param callable(string, string): string $callback
     *
     * @return array<string, string> old md5ext => new md5ext (only changed entries)
     */
    public function optimizeAssets(callable $callback): array
    {
        $remap = [];
        $newBytesByOld = [];
        foreach ($this->assets->entries() as $oldMd5ext => $oldBytes) {
            $newBytes = $callback($oldMd5ext, $oldBytes);
            if ($newBytes === $oldBytes) {
                continue;
            }
            $newMd5 = hash('md5', $newBytes);
            $extension = self::extensionOf($oldMd5ext);
            $newMd5ext = $extension !== '' ? "{$newMd5}.{$extension}" : $newMd5;
            if ($newMd5ext === $oldMd5ext) {
                // Bytes differ but md5+ext collide (impossible without hash
                // collision) — treat as a no-op to keep the index consistent.
                continue;
            }
            $remap[$oldMd5ext] = $newMd5ext;
            $newBytesByOld[$oldMd5ext] = $newBytes;
        }

        if ($remap === []) {
            return [];
        }

        // Rewrite the asset index: drop the old entries first so that two
        // assets optimizing to the same bytes collapse to a single entry.
        foreach (array_keys($remap) as $oldMd5ext) {
            $this->assets->remove((string) $oldMd5ext);
        }
        foreach ($remap as $oldMd5ext => $newMd5ext) {
            if (!$this->assets->has($newMd5ext)) {
                $this->assets->add($newMd5ext, $newBytesByOld[$oldMd5ext]);
            }
        }

        // Update references in every target's costumes + sounds.
        foreach ($this->data['targets'] as $targetIndex => $target) {
            if (!\is_array($target)) {
                continue;
            }
            foreach (['costumes', 'sounds'] as $field) {
                foreach (($target[$field] ?? []) as $i => $asset) {
                    $oldMd5ext = $asset['md5ext'] ?? null;
                    if (\is_string($oldMd5ext) && isset($remap[$oldMd5ext])) {
                        $newMd5ext = $remap[$oldMd5ext];
                        $this->data['targets'][$targetIndex][$field][$i]['md5ext'] = $newMd5ext;
                        $this->data['targets'][$targetIndex][$field][$i]['assetId'] = self::stripExtension($newMd5ext);
                    }
                }
            }
        }

        return $remap;
    }

    private static function extensionOf(string $md5ext): string
    {
        $dot = strrpos($md5ext, '.');

        return $dot === false ? '' : substr($md5ext, $dot + 1);
    }

    private static function stripExtension(string $md5ext): string
    {
        $dot = strrpos($md5ext, '.');

        return $dot === false ? $md5ext : substr($md5ext, 0, $dot);
    }

    /**
     * Remove asset entries that are not referenced by any costume/sound on any
     * target. Returns the list of md5ext keys that were dropped.
     *
     * @return list<string>
     */
    public function trimAssets(): array
    {
        $referenced = [];
        foreach ($this->data['targets'] ?? [] as $target) {
            if (!\is_array($target)) {
                continue;
            }
            foreach ([...($target['costumes'] ?? []), ...($target['sounds'] ?? [])] as $entry) {
                if (\is_array($entry) && \is_string($entry['md5ext'] ?? null)) {
                    $referenced[$entry['md5ext']] = true;
                }
            }
        }

        $removed = [];
        foreach (array_keys($this->assets->entries()) as $md5ext) {
            if (!isset($referenced[(string) $md5ext])) {
                $this->assets->remove((string) $md5ext);
                $removed[] = (string) $md5ext;
            }
        }

        return $removed;
    }

    /**
     * Extract a single sprite into a standalone `.sprite3` archive. The
     * resulting file can be dropped into Scratch's editor (or fed to
     * {@see importSprite()}) to add the sprite to another project.
     *
     * Only assets the sprite actually uses are bundled.
     *
     * @throws RuntimeException if no sprite with the given name exists
     */
    public function extractSprite(string $name, string $filePath): void
    {
        $sprite = null;
        foreach ($this->listSprites() as $candidate) {
            if (($candidate['name'] ?? null) === $name) {
                $sprite = $candidate;
                break;
            }
        }
        if (!\is_array($sprite)) {
            throw new RuntimeException(\sprintf('Sprite "%s" not found in project', $name));
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(\sprintf('Failed to create .sprite3 archive: %s', $filePath));
        }

        // A `.sprite3` stores a single sprite's JSON as `sprite.json`.
        $zip->addFromString('sprite.json', Json::encodeProjectData($sprite));
        foreach ([...($sprite['costumes'] ?? []), ...($sprite['sounds'] ?? [])] as $asset) {
            $md5ext = $asset['md5ext'] ?? null;
            if (!\is_string($md5ext)) {
                continue;
            }
            $data = $this->assets->get($md5ext);
            if ($data !== null) {
                $zip->addFromString($md5ext, $data);
            }
        }
        $zip->close();
    }

    /**
     * Add a sprite from a `.sprite3` archive into this project, returning a
     * {@see \CodeClubLuxembourg\Sb3Toolkit\Builder\Sprite} for further work. If the sprite's
     * name collides with an existing one, " (n)" is appended to keep names
     * unique. Bundled assets are added to the project's asset index.
     *
     * @throws RuntimeException if the archive can't be opened or is missing
     *                          `sprite.json`
     */
    public function importSprite(string $filePath): \CodeClubLuxembourg\Sb3Toolkit\Builder\Sprite
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException(\sprintf('Failed to open .sprite3 archive: %s', $filePath));
        }
        $spriteJson = $zip->getFromName('sprite.json');
        if ($spriteJson === false) {
            $zip->close();
            throw new RuntimeException(\sprintf('Missing sprite.json in %s', $filePath));
        }

        try {
            /** @var array<string, mixed> $sprite */
            $sprite = Json::decodeProjectData($spriteJson);
        } catch (JsonException $error) {
            $zip->close();
            throw new RuntimeException(\sprintf('Failed to parse sprite.json: %s', $error->getMessage()), 0, $error);
        }

        $existingNames = [];
        foreach ($this->data['targets'] as $target) {
            $existingNames[$target['name']] = true;
        }
        if (isset($existingNames[$sprite['name']])) {
            $sprite['name'] = self::defaultRenameStrategy($sprite['name'], $existingNames);
        }
        $sprite['isStage'] = false;
        $sprite['layerOrder'] = \count($this->listSprites()) + 1;

        // Copy bundled assets in.
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $entryName = $zip->getNameIndex($i);
            if ($entryName === false || $entryName === 'sprite.json' || str_ends_with($entryName, '/')) {
                continue;
            }
            $content = $zip->getFromIndex($i);
            if ($content !== false && !$this->assets->has($entryName)) {
                $this->assets->add($entryName, $content);
            }
        }
        $zip->close();

        $this->data['targets'][] = $sprite;
        $lastKey = array_key_last($this->data['targets']);
        $targetRef = &$this->data['targets'][$lastKey];

        return new \CodeClubLuxembourg\Sb3Toolkit\Builder\Sprite($targetRef, $this->assets);
    }

    /**
     * Create a merged project scaffold seeded with the base project's data.
     *
     * @return array{data: array<string, mixed>, assets: ScratchAssetIndex, names: array<string, true>}
     */
    private static function initMergedProject(self $base): array
    {
        $mergedData = $base->data;
        $mergedAssets = new ScratchAssetIndex();
        foreach ($base->assets->entries() as $md5ext => $data) {
            $mergedAssets->add($md5ext, $data);
        }

        $names = [];
        foreach ($mergedData['targets'] as $target) {
            $names[$target['name']] = true;
        }

        return ['data' => $mergedData, 'assets' => $mergedAssets, 'names' => $names];
    }

    /**
     * Copy all costume/sound assets referenced by a target into the merged assets.
     *
     * @param array<string, mixed> $target
     */
    private static function collectAssetsFromTarget(array $target, self $source, ScratchAssetIndex $mergedAssets): void
    {
        $refs = [...($target['costumes'] ?? []), ...($target['sounds'] ?? [])];
        foreach ($refs as $asset) {
            $md5ext = $asset['md5ext'] ?? null;
            if (!\is_string($md5ext) || $mergedAssets->has($md5ext)) {
                continue;
            }
            $data = $source->assets->get($md5ext);
            if ($data === null) {
                throw new RuntimeException(\sprintf('Missing asset %s in source project', $md5ext));
            }
            $mergedAssets->add($md5ext, $data);
        }
    }

    /**
     * Normalize sprite layer order: sprites stacked from 1, stage at 0.
     *
     * @param array<string, mixed> $data
     */
    private static function normalizeLayerOrder(array &$data): void
    {
        $sprites = [];
        foreach ($data['targets'] as $index => $target) {
            if (empty($target['isStage'])) {
                $sprites[] = ['index' => $index, 'layerOrder' => $target['layerOrder'] ?? 0];
            }
        }

        usort(
            $sprites,
            static fn(array $a, array $b): int => $a['layerOrder'] === $b['layerOrder']
                ? $a['index'] <=> $b['index']
                : $a['layerOrder'] <=> $b['layerOrder'],
        );

        $order = 1;
        foreach ($sprites as $sprite) {
            $data['targets'][$sprite['index']]['layerOrder'] = $order++;
        }

        foreach ($data['targets'] as $index => $target) {
            if (!empty($target['isStage'])) {
                $data['targets'][$index]['layerOrder'] = 0;
            }
        }
    }

    /**
     * Compute the stage offset for a given grid cell index.
     *
     * @return array{x: int|float, y: int|float}
     */
    private static function gridOffset(int $index, int $columns, int $rows): array
    {
        $column = $index % $columns;
        $row = intdiv($index, $columns);
        $x = ($column - ($columns - 1) / 2) * self::STAGE_WIDTH;
        $y = (($rows - 1) / 2 - $row) * self::STAGE_HEIGHT;

        return ['x' => self::normalizeNumber($x), 'y' => self::normalizeNumber($y)];
    }

    /**
     * Apply a uniform offset to every target in a project.
     *
     * @param array<string, mixed> $data
     */
    private static function applyGridOffset(array &$data, array $offset): void
    {
        foreach ($data['targets'] as $index => $target) {
            self::applyOffsetToTarget($target, $offset['x'], $offset['y']);
            $data['targets'][$index] = $target;
        }
    }

    /**
     * Apply a position offset to a single target and its absolute-coordinate blocks.
     *
     * @param array<string, mixed> $target
     */
    private static function applyOffsetToTarget(array &$target, int|float $dx, int|float $dy): void
    {
        if (empty($target['isStage'])) {
            $target['x'] = self::normalizeNumber(($target['x'] ?? 0) + $dx);
            $target['y'] = self::normalizeNumber(($target['y'] ?? 0) + $dy);
        }

        if (isset($target['blocks']) && \is_array($target['blocks'])) {
            self::applyOffsetToBlocks($target['blocks'], $dx, $dy);
        }
    }

    /**
     * Walk all blocks and offset any absolute coordinate inputs.
     *
     * @param array<string, mixed> $blocks
     */
    private static function applyOffsetToBlocks(array &$blocks, int|float $dx, int|float $dy): void
    {
        foreach ($blocks as $id => $block) {
            if (!\is_array($block)) {
                continue;
            }
            $mapping = self::ABSOLUTE_COORD_INPUTS[$block['opcode'] ?? ''] ?? null;
            if ($mapping === null) {
                continue;
            }

            if (isset($mapping['x']) && isset($block['inputs'][$mapping['x']])) {
                $block['inputs'][$mapping['x']][1] = self::offsetInputValue(
                    $block['inputs'][$mapping['x']][1],
                    $dx,
                    $mapping['xArrayAdjust'] ?? 0,
                    $mapping['coerceNumber'] ?? false,
                );
            }
            if (isset($mapping['y']) && isset($block['inputs'][$mapping['y']])) {
                $block['inputs'][$mapping['y']][1] = self::offsetInputValue(
                    $block['inputs'][$mapping['y']][1],
                    $dy,
                    $mapping['yArrayAdjust'] ?? 0,
                    $mapping['coerceNumber'] ?? false,
                );
            }

            $blocks[$id] = $block;
        }
    }

    /**
     * Offset an input value, preserving its original representation where possible.
     */
    private static function offsetInputValue(
        mixed $value,
        int|float $delta,
        int $arrayAdjust,
        bool $coerceNumber,
    ): mixed {
        if ($value === null) {
            return null;
        }

        if (\is_int($value) || \is_float($value)) {
            return self::normalizeNumber($value + $delta);
        }

        if (\is_string($value)) {
            if (is_numeric($value)) {
                $updated = self::normalizeNumber((float) $value + $delta);

                return $coerceNumber ? $updated : (string) $updated;
            }

            return $value;
        }

        if (\is_array($value) && \count($value) >= 2) {
            $count = \count($value);
            $adjustedDelta = $delta + $arrayAdjust;
            $raw = $value[1];

            if (\is_int($raw) || \is_float($raw)) {
                $new = self::normalizeNumber($raw + $adjustedDelta);

                return $count === 2 ? [$value[0], $new] : [$value[0], $new, $value[2]];
            }

            if (\is_string($raw) && is_numeric($raw)) {
                $updated = self::normalizeNumber((float) $raw + $adjustedDelta);
                $out = $coerceNumber ? $updated : (string) $updated;

                return $count === 2 ? [$value[0], $out] : [$value[0], $out, $value[2]];
            }
        }

        return $value;
    }

    /**
     * Generate a unique sprite name by appending " (n)".
     *
     * @param array<string, true> $existing
     */
    private static function defaultRenameStrategy(string $name, array $existing): string
    {
        $counter = 2;
        $nextName = \sprintf('%s (%d)', $name, $counter);
        while (isset($existing[$nextName])) {
            ++$counter;
            $nextName = \sprintf('%s (%d)', $name, $counter);
        }

        return $nextName;
    }

    /**
     * Coerce an integral float back to an int, mirroring JS number semantics.
     */
    private static function normalizeNumber(int|float $n): int|float
    {
        if (\is_float($n) && is_finite($n) && floor($n) === $n && abs($n) < (float) \PHP_INT_MAX) {
            return (int) $n;
        }

        return $n;
    }

    /**
     * Parse project JSON text and validate it has the expected structure.
     *
     * @return array<string, mixed>
     */
    private static function parseProjectData(string $projectText): array
    {
        try {
            $data = Json::decodeProjectData($projectText);
        } catch (JsonException $error) {
            throw new RuntimeException(\sprintf('Failed to parse project.json: %s', $error->getMessage()), 0, $error);
        }

        if (!isset($data['targets']) || !\is_array($data['targets'])) {
            throw new RuntimeException('Invalid project.json (missing targets array).');
        }

        return $data;
    }
}
