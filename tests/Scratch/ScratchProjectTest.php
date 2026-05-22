<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Scratch;

use PHPUnit\Framework\TestCase;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Events;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Motion;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ScratchValidator;
use ZipArchive;

final class ScratchProjectTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/scratch-' . bin2hex(random_bytes(6)) . '.sb3';
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testSavesAndReloadsRoundTrips(): void
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite('Cat');
        $cat->addScript(Events::whenFlagClicked()->then(Motion::moveSteps(10)));

        $project->saveToSb3File($this->tmpFile);
        self::assertFileExists($this->tmpFile);

        $reloaded = ScratchProject::fromSb3File($this->tmpFile);
        self::assertCount(1, $reloaded->listSprites());
        self::assertSame('Cat', $reloaded->listSprites()[0]['name']);
        self::assertTrue(ScratchValidator::validate($reloaded, assets: $reloaded->assets)->isValid);
    }

    public function testEmptyMapsSerializeAsJsonObjects(): void
    {
        $project = ScratchProject::create();
        $project->saveToSb3File($this->tmpFile);

        $zip = new ZipArchive();
        $zip->open($this->tmpFile);
        $json = $zip->getFromName('project.json');
        $zip->close();

        // Empty Scratch maps must remain objects, not collapse into arrays.
        self::assertStringContainsString('"variables":{}', $json);
        self::assertStringContainsString('"blocks":{}', $json);
        self::assertStringNotContainsString('"variables":[]', $json);
    }

    public function testMissingProjectJsonThrows(): void
    {
        $zip = new ZipArchive();
        $zip->open($this->tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('not-project.txt', 'x');
        $zip->close();

        $this->expectExceptionMessage('Missing project.json');
        ScratchProject::fromSb3File($this->tmpFile);
    }
}
