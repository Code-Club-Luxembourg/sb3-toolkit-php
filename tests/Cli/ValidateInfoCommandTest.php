<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Cli;

use PHPUnit\Framework\TestCase;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Events;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Motion;
use CodeClubLuxembourg\Sb3Toolkit\Cli\CliFactory;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use Symfony\Component\Console\Tester\CommandTester;

final class ValidateInfoCommandTest extends TestCase
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

    public function testValidateCommandOnCleanProjectExitsZero(): void
    {
        $project = ScratchProject::create();
        $project->addSprite('Cat')->addScript(Events::whenFlagClicked()->then(Motion::moveSteps(10)));
        $path = $this->tempPath();
        $project->saveToSb3File($path);

        $cmd = CliFactory::create()->find('validate');
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['files' => [$path]]);
        self::assertSame(0, $exit, $tester->getDisplay());
        self::assertStringContainsString('0 error(s)', $tester->getDisplay());
    }

    public function testValidateSemanticReportsUnusedVariable(): void
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite('Cat');
        $cat->addVariable('unused');
        $cat->addScript(Events::whenFlagClicked());
        $path = $this->tempPath();
        $project->saveToSb3File($path);

        $cmd = CliFactory::create()->find('validate');
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['files' => [$path], '--semantic' => true]);
        self::assertSame(0, $exit); // warning only — still passes
        self::assertStringContainsString('UNUSED_VARIABLE', $tester->getDisplay());
    }

    public function testValidateWarningsAsErrorsFails(): void
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite('Cat');
        $cat->addVariable('unused');
        $cat->addScript(Events::whenFlagClicked());
        $path = $this->tempPath();
        $project->saveToSb3File($path);

        $cmd = CliFactory::create()->find('validate');
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([
            'files' => [$path],
            '--semantic' => true,
            '--warnings-as-errors' => true,
        ]);
        self::assertSame(1, $exit);
    }

    public function testInfoCommandPrintsSummary(): void
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite('Cat');
        $cat->addScript(Events::whenFlagClicked()->then(Motion::moveSteps(10)));
        $cat->addScript(Events::whenKeyPressed(\CodeClubLuxembourg\Sb3Toolkit\Builder\Field\Key::Space));
        $cat->addVariable('score');
        $project->addBroadcast('start');
        $path = $this->tempPath();
        $project->saveToSb3File($path);

        $cmd = CliFactory::create()->find('info');
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['files' => [$path]]);
        self::assertSame(0, $exit);

        $output = $tester->getDisplay();
        self::assertStringContainsString('1 stage + 1 sprite(s)', $output);
        self::assertStringContainsString('motion_movesteps', $output);
        self::assertStringContainsString('Broadcasts: 1', $output);
        self::assertStringContainsString('Cat', $output);
    }
}
