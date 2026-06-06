<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Scratch;

use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Events;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Looks;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Packager\HtmlPackager;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Packager\PackRequest;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchAssetIndex;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use PHPUnit\Framework\TestCase;

final class InMemoryPartsTest extends TestCase
{
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function tmp(string $suffix): string
    {
        $path = sys_get_temp_dir() . '/inmem-' . bin2hex(random_bytes(6)) . $suffix;
        $this->tmpFiles[] = $path;

        return $path;
    }

    private function sample(): ScratchProject
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite('Cat');
        $cat->addScript(Events::whenFlagClicked()->then(Looks::say('hi')));

        return $project;
    }

    public function testAssetIndexBulkConstructor(): void
    {
        $index = new ScratchAssetIndex(['abc.svg' => '<svg/>', 'def.png' => 'PNGBYTES']);

        self::assertSame(2, $index->size());
        self::assertTrue($index->has('abc.svg'));
        self::assertSame('PNGBYTES', $index->get('def.png'));
        self::assertSame(['abc.svg' => '<svg/>', 'def.png' => 'PNGBYTES'], $index->entries());
    }

    public function testFromPartsRoundTripsThroughToSb3Bytes(): void
    {
        $original = $this->sample();
        $data = $original->data;
        $assets = $original->assets->entries();

        // Rebuild purely from the in-memory parts (no file, no zip parse).
        $rebuilt = ScratchProject::fromParts($data, $assets);

        // Serialize to bytes in memory, then read back to confirm fidelity.
        $bytes = $rebuilt->toSb3Bytes();
        self::assertStringStartsWith('PK', $bytes); // zip signature

        $path = $this->tmp('.sb3');
        file_put_contents($path, $bytes);
        $reloaded = ScratchProject::fromSb3File($path);

        self::assertSame($data, $reloaded->data);
        self::assertSame($assets, $reloaded->assets->entries());
    }

    public function testPackagerAcceptsProjectBytesWithoutAFile(): void
    {
        $bytes = $this->sample()->toSb3Bytes();

        $out = $this->tmp('.html');
        (new HtmlPackager())->pack(new PackRequest(
            projectPath: '',          // ignored when projectBytes is set
            outputPath: $out,
            stageWidth: 480,
            stageHeight: 360,
            projectBytes: $bytes,
        ));

        $html = (string) file_get_contents($out);
        self::assertSame(1, preg_match('/const PROJECT_DATA = "([A-Za-z0-9+\/=]+)"/', $html, $m));
        self::assertSame(base64_encode($bytes), $m[1]);
    }
}
