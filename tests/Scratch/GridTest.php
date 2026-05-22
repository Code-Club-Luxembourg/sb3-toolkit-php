<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Scratch;

use PHPUnit\Framework\TestCase;
use CodeClubLuxembourg\Sb3Toolkit\Cli\NumberParser;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchAssetIndex;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use CodeClubLuxembourg\Sb3Toolkit\Tests\Support\Fixtures;

final class GridTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function blocks(): array
    {
        return [
            'gotoxy' => ['opcode' => 'motion_gotoxy', 'next' => null, 'parent' => null, 'inputs' => ['X' => [1, [4, '0']], 'Y' => [1, [4, '0']]], 'fields' => [], 'shadow' => false, 'topLevel' => false],
            'setx' => ['opcode' => 'motion_setx', 'next' => null, 'parent' => null, 'inputs' => ['X' => [1, [4, '10']]], 'fields' => [], 'shadow' => false, 'topLevel' => false],
            'sety' => ['opcode' => 'motion_sety', 'next' => null, 'parent' => null, 'inputs' => ['Y' => [1, [4, '5']]], 'fields' => [], 'shadow' => false, 'topLevel' => false],
            'glide' => ['opcode' => 'motion_glidesecstoxy', 'next' => null, 'parent' => null, 'inputs' => ['SECS' => [1, [4, 1]], 'X' => [1, [4, 30]], 'Y' => [1, [4, -10]]], 'fields' => [], 'shadow' => false, 'topLevel' => false],
        ];
    }

    private function makeProject(string $name): ScratchProject
    {
        $sprite = Fixtures::sprite($name, [
            'blocks' => $this->blocks(),
            'costumes' => [Fixtures::costume("{$name}-costume")],
            'sounds' => [Fixtures::sound("{$name}-sound")],
            'x' => 10,
            'y' => 20,
        ]);
        $data = Fixtures::projectData([Fixtures::stage([Fixtures::costume('stage-costume')], [Fixtures::sound('stage-sound')]), $sprite]);

        $assets = new ScratchAssetIndex();
        $assets->add('stage-costume.svg', 'stage-costume');
        $assets->add('stage-sound.wav', 'stage-sound');
        $assets->add("{$name}-costume.svg", $name);
        $assets->add("{$name}-sound.wav", $name);

        return new ScratchProject($data, $assets);
    }

    public function testOffsetsSpritePositionsAndAbsoluteCoordinateInputs(): void
    {
        $grid = ScratchProject::gridProjects([$this->makeProject('Left'), $this->makeProject('Right')], 2);

        $sprites = [];
        foreach ($grid->listSprites() as $sprite) {
            $sprites[$sprite['name']] = $sprite;
        }
        $left = $sprites['Left'];
        $right = $sprites['Right'];

        self::assertSame(-230, $left['x']);
        self::assertSame(20, $left['y']);
        self::assertSame(250, $right['x']);
        self::assertSame(20, $right['y']);

        self::assertSame([4, -140], $left['blocks']['gotoxy']['inputs']['X'][1]);
        self::assertSame([4, 340], $right['blocks']['gotoxy']['inputs']['X'][1]);
        self::assertSame([4, -50], $left['blocks']['gotoxy']['inputs']['Y'][1]);
        self::assertSame([4, -50], $right['blocks']['gotoxy']['inputs']['Y'][1]);
        self::assertSame([4, '-230'], $left['blocks']['setx']['inputs']['X'][1]);
        self::assertSame([4, '250'], $right['blocks']['setx']['inputs']['X'][1]);
        self::assertSame([4, '5'], $left['blocks']['sety']['inputs']['Y'][1]);
        self::assertSame([4, '5'], $right['blocks']['sety']['inputs']['Y'][1]);
        self::assertSame([4, -210], $left['blocks']['glide']['inputs']['X'][1]);
        self::assertSame([4, 270], $right['blocks']['glide']['inputs']['X'][1]);
        self::assertSame([4, -10], $left['blocks']['glide']['inputs']['Y'][1]);
        self::assertSame([4, -10], $right['blocks']['glide']['inputs']['Y'][1]);
    }

    public function testReportsGridSizeInPixels(): void
    {
        self::assertSame(['width' => 960, 'height' => 1080], ScratchProject::gridPixelSize(2, 3));
    }

    public function testRejectsInvalidRowCounts(): void
    {
        $this->expectExceptionMessage('Grid rows must be a positive number');
        ScratchProject::gridProjects([$this->makeProject('Only')], 2, 0);
    }

    public function testRejectsNonNumericCliValuesForRows(): void
    {
        $this->expectExceptionMessage('Grid rows must be a positive integer.');
        NumberParser::positiveInt('nope', 'Grid rows');
    }
}
