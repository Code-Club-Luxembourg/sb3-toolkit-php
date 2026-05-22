<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Scratch;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Events;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;

final class AssetHygieneTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function tempPath(string $suffix = '.sb3'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sb3') . $suffix;
        $this->tmpFiles[] = $path;

        return $path;
    }

    public function testTrimAssetsRemovesUnreferenced(): void
    {
        $project = ScratchProject::create();
        $project->addSprite('Cat');
        // Add an asset that no costume/sound references.
        $project->assets->add('orphan.svg', '<svg/>');

        self::assertSame(1, $project->assets->size() - 1); // 1 referenced (blank) + 1 orphan
        $removed = $project->trimAssets();
        self::assertSame(['orphan.svg'], $removed);
        self::assertFalse($project->assets->has('orphan.svg'));
    }

    public function testTrimAssetsKeepsReferenced(): void
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite('Cat');
        $cat->addCostume('extra', '<svg viewBox="0 0 1 1"/>');

        $before = $project->assets->size();
        $removed = $project->trimAssets();
        self::assertSame([], $removed);
        self::assertSame($before, $project->assets->size());
    }

    public function testExtractAndImportSpriteRoundTrip(): void
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite('Cat');
        $cat->addScript(Events::whenFlagClicked());
        $cat->addCostume('extra', '<svg viewBox="0 0 1 1"/>');

        $spritePath = $this->tempPath('.sprite3');
        $project->extractSprite('Cat', $spritePath);
        self::assertFileExists($spritePath);

        // Import into a fresh project.
        $target = ScratchProject::create();
        $sprite = $target->importSprite($spritePath);
        self::assertSame('Cat', $sprite->name());

        // The custom costume's asset should have come along.
        $costumes = $sprite->data()['costumes'];
        $hasExtra = false;
        foreach ($costumes as $costume) {
            if ($costume['name'] === 'extra') {
                self::assertTrue($target->assets->has($costume['md5ext']));
                $hasExtra = true;
            }
        }
        self::assertTrue($hasExtra);
    }

    public function testImportSpriteRenamesOnCollision(): void
    {
        $project = ScratchProject::create();
        $project->addSprite('Cat');
        $project->addSprite('Cat (2)'); // pre-existing collision target

        // Build a single-sprite project named Cat and extract it.
        $source = ScratchProject::create();
        $source->addSprite('Cat');
        $spritePath = $this->tempPath('.sprite3');
        $source->extractSprite('Cat', $spritePath);

        $imported = $project->importSprite($spritePath);
        // First free slot is (3): Cat, Cat (2), and now Cat (3).
        self::assertSame('Cat (3)', $imported->name());
    }

    public function testExtractMissingSpriteThrows(): void
    {
        $this->expectException(RuntimeException::class);
        ScratchProject::create()->extractSprite('Nonexistent', $this->tempPath('.sprite3'));
    }

    public function testOptimizeAssetsRunsCallbackOnEveryEntry(): void
    {
        $project = ScratchProject::create();
        $project->addSprite('Cat');
        $seen = [];
        $remap = $project->optimizeAssets(function (string $md5ext, string $data) use (&$seen): string {
            $seen[] = $md5ext;

            return $data; // identity: no remap
        });
        self::assertNotEmpty($seen);
        self::assertSame([], $remap);
    }

    public function testOptimizeAssetsRehashesChangedBytesAndUpdatesReferences(): void
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite('Cat');
        $oldMd5ext = $cat->data()['costumes'][0]['md5ext'];

        // Optimizer that rewrites SVG bytes — the resulting hash must
        // propagate into both the asset index and the costume reference.
        $remap = $project->optimizeAssets(
            static fn (string $md5ext, string $data): string => '<svg viewBox="0 0 1 1"/>',
        );

        self::assertArrayHasKey($oldMd5ext, $remap);
        $newMd5ext = $remap[$oldMd5ext];
        self::assertNotSame($oldMd5ext, $newMd5ext);
        self::assertSame('svg', pathinfo($newMd5ext, \PATHINFO_EXTENSION));

        // Asset index: new key present, old gone.
        self::assertTrue($project->assets->has($newMd5ext));
        self::assertFalse($project->assets->has($oldMd5ext));

        // Costume references: updated md5ext + assetId, both on the sprite
        // (the new costume entry from addSprite) and on the stage backdrop
        // (which shared the same blank costume's md5ext).
        foreach ($project->data['targets'] as $target) {
            foreach ($target['costumes'] ?? [] as $costume) {
                self::assertSame($newMd5ext, $costume['md5ext']);
                self::assertSame(substr($newMd5ext, 0, -4), $costume['assetId']);
            }
        }
    }

    public function testOptimizeAssetsSkipsIdentityReturns(): void
    {
        $project = ScratchProject::create();
        $project->addSprite('Cat');
        $remap = $project->optimizeAssets(static fn (string $md5ext, string $data): string => $data);
        self::assertSame([], $remap);
    }

    public function testOptimizeAssetsCollapsesIdenticalOutputs(): void
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite('Cat');
        // Add a second, distinct costume so we have two assets to start with.
        $cat->addCostume('extra', '<svg/>');
        $assetsBefore = \count($project->assets->entries());
        self::assertGreaterThanOrEqual(2, $assetsBefore);

        // Optimizer that rewrites every asset to the same bytes — the index
        // should collapse to one entry.
        $project->optimizeAssets(static fn (): string => '<svg viewBox="0 0 1 1"/>');
        self::assertCount(1, $project->assets->entries());
    }

    public function testOptimizeAssetsPreservesFileExtension(): void
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite('Cat');
        $cat->addCostume('pngOne', 'pngbytes', 'png');
        $assetsBefore = $project->assets->entries();
        $pngKey = '';
        foreach (array_keys($assetsBefore) as $key) {
            if (str_ends_with((string) $key, '.png')) {
                $pngKey = (string) $key;
                break;
            }
        }
        self::assertNotSame('', $pngKey);

        $remap = $project->optimizeAssets(
            static fn (string $md5ext, string $data): string => str_ends_with($md5ext, '.png') ? 'newpng' : $data,
        );
        self::assertArrayHasKey($pngKey, $remap);
        self::assertStringEndsWith('.png', $remap[$pngKey]);
    }
}
