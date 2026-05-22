<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Scratch;

use PHPUnit\Framework\TestCase;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchAssetIndex;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use CodeClubLuxembourg\Sb3Toolkit\Tests\Support\Fixtures;

final class MergeTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function spriteWithAssets(string $name, int $layerOrder, string $costumeId, string $soundId): array
    {
        return Fixtures::sprite($name, [
            'layerOrder' => $layerOrder,
            'costumes' => [Fixtures::costume($costumeId)],
            'sounds' => [Fixtures::sound($soundId)],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function stageWithAssets(): array
    {
        return Fixtures::stage([Fixtures::costume('stage-costume')], [Fixtures::sound('stage-sound')]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function spriteNames(ScratchProject $project): array
    {
        $names = [];
        foreach ($project->data['targets'] as $target) {
            if (empty($target['isStage'])) {
                $names[] = $target['name'];
            }
        }

        return $names;
    }

    /**
     * @return array<string, int>
     */
    private function layerByName(ScratchProject $project): array
    {
        $map = [];
        foreach ($project->data['targets'] as $target) {
            if (empty($target['isStage'])) {
                $map[$target['name']] = $target['layerOrder'];
            }
        }

        return $map;
    }

    public function testMergesSpritesRenamesCollisionsAndNormalizesLayerOrder(): void
    {
        $baseData = Fixtures::projectData([$this->stageWithAssets(), $this->spriteWithAssets('Cat', 5, 'base-costume', 'base-sound')]);
        $baseAssets = new ScratchAssetIndex();
        foreach (['stage-costume.svg', 'base-costume.svg'] as $a) {
            $baseAssets->add($a, $a);
        }
        foreach (['stage-sound.wav', 'base-sound.wav'] as $a) {
            $baseAssets->add($a, $a);
        }
        $baseProject = new ScratchProject($baseData, $baseAssets);

        $sourceData = Fixtures::projectData([$this->stageWithAssets(), $this->spriteWithAssets('Cat', 2, 'source-costume', 'source-sound')]);
        $sourceAssets = new ScratchAssetIndex();
        $sourceAssets->add('source-costume.svg', 'source-costume');
        $sourceAssets->add('source-sound.wav', 'source-sound');
        $sourceProject = new ScratchProject($sourceData, $sourceAssets);

        $merged = ScratchProject::mergeSprites($baseProject, [$sourceProject]);

        self::assertSame(['Cat', 'Cat (2)'], $this->spriteNames($merged));
        self::assertSame(0, $merged->getStage()['layerOrder']);

        $layers = $this->layerByName($merged);
        self::assertSame(1, $layers['Cat (2)']);
        self::assertSame(2, $layers['Cat']);

        self::assertTrue($merged->assets->has('source-costume.svg'));
        self::assertTrue($merged->assets->has('source-sound.wav'));
    }

    public function testToleratesNullBlocksWhenApplyingGridOffsets(): void
    {
        $baseData = Fixtures::projectData([$this->stageWithAssets(), $this->spriteWithAssets('Cat', 1, 'base-costume', 'base-sound')]);
        $baseAssets = new ScratchAssetIndex();
        foreach (['stage-costume.svg', 'base-costume.svg'] as $a) {
            $baseAssets->add($a, $a);
        }
        foreach (['stage-sound.wav', 'base-sound.wav'] as $a) {
            $baseAssets->add($a, $a);
        }
        $baseProject = new ScratchProject($baseData, $baseAssets);

        $sourceSprite = $this->spriteWithAssets('Dog', 1, 'source-costume', 'source-sound');
        $sourceSprite['blocks'] = [
            'root' => [
                'opcode' => 'motion_gotoxy',
                'next' => null,
                'parent' => null,
                'inputs' => ['X' => [1, 10], 'Y' => [1, -20]],
                'fields' => [],
                'shadow' => false,
                'topLevel' => true,
            ],
            'dangling' => null,
        ];
        $sourceData = Fixtures::projectData([$this->stageWithAssets(), $sourceSprite]);
        $sourceAssets = new ScratchAssetIndex();
        $sourceAssets->add('source-costume.svg', 'source-costume');
        $sourceAssets->add('source-sound.wav', 'source-sound');
        $sourceProject = new ScratchProject($sourceData, $sourceAssets);

        $grid = ScratchProject::gridProjects([$baseProject, $sourceProject], 2);
        $dog = null;
        foreach ($grid->listSprites() as $sprite) {
            if ($sprite['name'] === 'Dog') {
                $dog = $sprite;
            }
        }

        self::assertNotNull($dog);
        self::assertSame(250, $dog['blocks']['root']['inputs']['X'][1]);
        self::assertSame(-20, $dog['blocks']['root']['inputs']['Y'][1]);
    }

    public function testPreservesLayerOrderingBasedOnOriginalValues(): void
    {
        $data = Fixtures::projectData([
            $this->stageWithAssets(),
            $this->spriteWithAssets('Front', 2, 'front-costume', 'front-sound'),
            $this->spriteWithAssets('Back', 1, 'back-costume', 'back-sound'),
        ]);
        $merged = ScratchProject::mergeSprites(new ScratchProject($data, new ScratchAssetIndex()), []);
        $layers = $this->layerByName($merged);
        self::assertSame(1, $layers['Back']);
        self::assertSame(2, $layers['Front']);
    }

    public function testKeepsStableOrderForEqualLayerOrder(): void
    {
        $data = Fixtures::projectData([
            $this->stageWithAssets(),
            $this->spriteWithAssets('First', 1, 'first-costume', 'first-sound'),
            $this->spriteWithAssets('Second', 1, 'second-costume', 'second-sound'),
        ]);
        $merged = ScratchProject::mergeSprites(new ScratchProject($data, new ScratchAssetIndex()), []);
        $layers = $this->layerByName($merged);
        self::assertSame(1, $layers['First']);
        self::assertSame(2, $layers['Second']);
    }
}
