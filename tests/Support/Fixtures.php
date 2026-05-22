<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Support;

use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchAssetIndex;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;

/**
 * Builders for project data used across the test suite.
 */
final class Fixtures
{
    /**
     * @return array<string, mixed>
     */
    public static function costume(string $id): array
    {
        return [
            'name' => "costume-{$id}",
            'dataFormat' => 'svg',
            'assetId' => $id,
            'md5ext' => "{$id}.svg",
            'rotationCenterX' => 0,
            'rotationCenterY' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sound(string $id): array
    {
        return [
            'name' => "sound-{$id}",
            'dataFormat' => 'wav',
            'assetId' => $id,
            'md5ext' => "{$id}.wav",
            'rate' => 44100,
            'sampleCount' => 1000,
        ];
    }

    /**
     * @param list<array<string, mixed>>|null $costumes
     * @param list<array<string, mixed>>      $sounds
     *
     * @return array<string, mixed>
     */
    public static function stage(?array $costumes = null, array $sounds = []): array
    {
        return [
            'isStage' => true,
            'name' => 'Stage',
            'variables' => [],
            'lists' => [],
            'broadcasts' => [],
            'blocks' => [],
            'comments' => [],
            'currentCostume' => 0,
            'costumes' => $costumes ?? [self::costume('stage-bg')],
            'sounds' => $sounds,
            'volume' => 100,
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    public static function sprite(string $name, array $overrides = []): array
    {
        return [
            'isStage' => false,
            'name' => $name,
            'variables' => [],
            'lists' => [],
            'broadcasts' => [],
            'blocks' => [],
            'comments' => [],
            'currentCostume' => 0,
            'costumes' => [self::costume("{$name}-costume")],
            'sounds' => [],
            'volume' => 100,
            'layerOrder' => 1,
            'visible' => true,
            'x' => 0,
            'y' => 0,
            'size' => 100,
            'direction' => 90,
            'draggable' => false,
            'rotationStyle' => 'all around',
            ...$overrides,
        ];
    }

    /**
     * @param list<array<string, mixed>> $targets
     *
     * @return array<string, mixed>
     */
    public static function projectData(array $targets): array
    {
        return [
            'targets' => $targets,
            'monitors' => [],
            'extensions' => [],
            'meta' => ['semver' => '3.0.0', 'vm' => '0.2.0'],
        ];
    }

    /**
     * Build a project, seeding the asset index from every target's costumes.
     *
     * @param list<array<string, mixed>> $targets
     */
    public static function makeProject(array $targets): ScratchProject
    {
        $assets = new ScratchAssetIndex();
        foreach ($targets as $target) {
            foreach ($target['costumes'] ?? [] as $costume) {
                $assets->add($costume['md5ext'], '');
            }
        }

        return new ScratchProject(self::projectData($targets), $assets);
    }
}
