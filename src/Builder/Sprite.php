<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder;

use RuntimeException;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\RotationStyle;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchAssetIndex;

/**
 * A reference to a sprite target, for adding scripts, costumes and variables
 * and for querying its blocks. Mutations write through to the live project.
 *
 * Shared script/variable/list operations live on {@see Scriptable}; this class
 * adds the sprite-only setters and costume support.
 */
final class Sprite extends Scriptable
{
    /**
     * @param array<string, mixed> $target the live sprite target (by reference)
     */
    public function __construct(array &$target, ScratchAssetIndex $assets)
    {
        parent::__construct($target, $assets);
    }

    /**
     * Set the active costume by index (matches `currentCostume` in `.sb3`).
     *
     * @throws RuntimeException when the index is outside `0..count(costumes)-1`
     */
    public function setCurrentCostume(int $index): self
    {
        $count = \count($this->target['costumes'] ?? []);
        if ($index < 0 || $index >= $count) {
            throw new RuntimeException(\sprintf(
                'Costume index %d is out of range (0..%d) on sprite "%s"',
                $index,
                $count - 1,
                $this->target['name'],
            ));
        }
        $this->target['currentCostume'] = $index;

        return $this;
    }

    public function setPosition(int|float $x, int|float $y): self
    {
        $this->target['x'] = $x;
        $this->target['y'] = $y;

        return $this;
    }

    public function setSize(int|float $size): self
    {
        $this->target['size'] = $size;

        return $this;
    }

    public function setDirection(int|float $direction): self
    {
        $this->target['direction'] = $direction;

        return $this;
    }

    public function setVisible(bool $visible): self
    {
        $this->target['visible'] = $visible;

        return $this;
    }

    public function setDraggable(bool $draggable): self
    {
        $this->target['draggable'] = $draggable;

        return $this;
    }

    public function setRotationStyle(RotationStyle $style): self
    {
        $this->target['rotationStyle'] = $style->value;

        return $this;
    }

    public function setVolume(int|float $volume): self
    {
        $this->target['volume'] = $volume;

        return $this;
    }

    /**
     * Add a costume from raw image data and return its index.
     */
    public function addCostume(string $name, string $data, string $dataFormat = 'svg'): int
    {
        $assetId = hash('md5', $data);
        $md5ext = "{$assetId}.{$dataFormat}";
        $this->assets->add($md5ext, $data);
        $this->target['costumes'][] = [
            'name' => $name,
            'dataFormat' => $dataFormat,
            'assetId' => $assetId,
            'md5ext' => $md5ext,
            'rotationCenterX' => 0,
            'rotationCenterY' => 0,
        ];

        return \count($this->target['costumes']) - 1;
    }

    /**
     * Remove the costume at $index, re-indexing the remaining costumes and
     * keeping `currentCostume` pointing at a valid entry. Useful for dropping
     * the blank placeholder costume that addSprite() creates by default.
     */
    public function removeCostume(int $index): self
    {
        array_splice($this->target['costumes'], $index, 1);

        $current = $this->target['currentCostume'] ?? 0;
        if ($current === $index) {
            $this->target['currentCostume'] = 0;
        } elseif ($current > $index) {
            $this->target['currentCostume'] = $current - 1;
        }

        return $this;
    }
}
