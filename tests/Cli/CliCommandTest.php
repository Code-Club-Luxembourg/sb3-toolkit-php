<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Cli;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Events;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Motion;
use CodeClubLuxembourg\Sb3Toolkit\Cli\CliFactory;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use Symfony\Component\Console\Tester\CommandTester;

final class CliCommandTest extends TestCase
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

    private function tmp(string $suffix): string
    {
        $path = sys_get_temp_dir() . '/scratch-cli-' . bin2hex(random_bytes(6)) . $suffix;
        $this->tmpFiles[] = $path;

        return $path;
    }

    private function writeProject(string $spriteName): string
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite($spriteName);
        $cat->addScript(Events::whenFlagClicked()->then(Motion::moveSteps(10)));
        $path = $this->tmp('.sb3');
        $project->saveToSb3File($path);

        return $path;
    }

    private function tester(string $command): CommandTester
    {
        return new CommandTester(CliFactory::create()->find($command));
    }

    public function testGridRejectsInvalidColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Grid columns must be a positive integer.');
        $this->tester('grid')->execute([
            'sources' => ['one.sb3'],
            '--columns' => 'nope',
            '--out' => 'out.sb3',
        ]);
    }

    public function testGridRejectsInvalidRows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Grid rows must be a positive integer.');
        $this->tester('grid')->execute([
            'sources' => ['one.sb3'],
            '--columns' => '2',
            '--rows' => 'bad',
            '--out' => 'out.sb3',
        ]);
    }

    public function testMergeProducesOutputFile(): void
    {
        $base = $this->writeProject('Cat');
        $source = $this->writeProject('Dog');
        $out = $this->tmp('.sb3');

        $tester = $this->tester('merge');
        $exit = $tester->execute([
            'base' => $base,
            'sources' => [$source],
            '--out' => $out,
        ]);

        self::assertSame(0, $exit);
        self::assertFileExists($out);
        self::assertStringContainsString('Merged 1 project(s)', $tester->getDisplay());

        $merged = ScratchProject::fromSb3File($out);
        $names = array_map(static fn(array $s): string => $s['name'], $merged->listSprites());
        self::assertContains('Cat', $names);
        self::assertContains('Dog', $names);
    }

    public function testGridProducesOutputFile(): void
    {
        $left = $this->writeProject('Left');
        $right = $this->writeProject('Right');
        $out = $this->tmp('.sb3');

        $tester = $this->tester('grid');
        $exit = $tester->execute([
            'sources' => [$left, $right],
            '--columns' => '2',
            '--out' => $out,
        ]);

        self::assertSame(0, $exit);
        self::assertFileExists($out);
        self::assertStringContainsString('Gridded 2 project(s)', $tester->getDisplay());
    }
}
