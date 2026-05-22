<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder;

use RuntimeException;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchAssetIndex;

/**
 * A reference to the stage target, for adding stage scripts, backdrops and
 * global variables/lists. Mutations write through to the live project.
 *
 * Shared script/variable/list operations live on {@see Scriptable}; this class
 * adds backdrop support. The convenience methods on
 * {@see \CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject} (`addBackdrop`,
 * `setCurrentBackdrop`, `addVariable`, `addList`) continue to work and now
 * delegate to a `Stage` under the hood.
 */
final class Stage extends Scriptable
{
    /**
     * @param array<string, mixed> $target the live stage target (by reference)
     */
    public function __construct(array &$target, ScratchAssetIndex $assets)
    {
        parent::__construct($target, $assets);
    }

    /**
     * Set the active backdrop by index (matches `currentCostume` on the stage).
     *
     * @throws RuntimeException when the index is outside `0..count(costumes)-1`
     */
    public function setCurrentBackdrop(int $index): self
    {
        $count = \count($this->target['costumes'] ?? []);
        if ($index < 0 || $index >= $count) {
            throw new RuntimeException(\sprintf(
                'Backdrop index %d is out of range (0..%d)',
                $index,
                $count - 1,
            ));
        }
        $this->target['currentCostume'] = $index;

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
        $this->target['costumes'][] = [
            'name' => $name,
            'dataFormat' => $dataFormat,
            'assetId' => $assetId,
            'md5ext' => $md5ext,
            'rotationCenterX' => 240,
            'rotationCenterY' => 180,
        ];

        return \count($this->target['costumes']) - 1;
    }
}
