<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Cli;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Events;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Looks;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Packager\BridgeScript;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Packager\HtmlPackager;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Packager\PackRequest;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;

final class HtmlPackagerTest extends TestCase
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
        $path = sys_get_temp_dir() . '/htmlpack-' . bin2hex(random_bytes(6)) . $suffix;
        $this->tmpFiles[] = $path;

        return $path;
    }

    private function sampleProject(): string
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite('Cat');
        $cat->addScript(Events::whenFlagClicked()->then(Looks::say('hi')));
        $path = $this->tmp('.sb3');
        $project->saveToSb3File($path);

        return $path;
    }

    public function testProducesStandalonePlayer(): void
    {
        $out = $this->tmp('.html');
        (new HtmlPackager())->pack(new PackRequest(
            projectPath: $this->sampleProject(),
            outputPath: $out,
            stageWidth: 640,
            stageHeight: 480,
            autoplay: true,
        ));

        $html = (string) file_get_contents($out);

        self::assertStringStartsWith('<!DOCTYPE html>', $html);
        self::assertStringContainsString('new Scaffolding.Scaffolding()', $html);
        self::assertStringContainsString('Scaffolding.JSZip.loadAsync', $html);
        self::assertStringContainsString('scaffolding.loadProject', $html);
        self::assertStringContainsString('<div id="app">', $html);
        self::assertStringContainsString('scaffolding.width = 640;', $html);
        self::assertStringContainsString('scaffolding.height = 480;', $html);
        self::assertStringContainsString('if (true)', $html); // autoplay
    }

    public function testEmbeddedProjectIsADecodableZip(): void
    {
        $out = $this->tmp('.html');
        (new HtmlPackager())->pack(new PackRequest(
            projectPath: $this->sampleProject(),
            outputPath: $out,
            stageWidth: 480,
            stageHeight: 360,
        ));

        $html = (string) file_get_contents($out);
        self::assertSame(1, preg_match('/const PROJECT_DATA = "([A-Za-z0-9+\/=]+)"/', $html, $m));
        $bytes = base64_decode($m[1], true);
        self::assertNotFalse($bytes);
        self::assertStringStartsWith('PK', $bytes); // zip signature
    }

    public function testAutoplayFalseUsesLaunchScreen(): void
    {
        $out = $this->tmp('.html');
        (new HtmlPackager())->pack(new PackRequest(
            projectPath: $this->sampleProject(),
            outputPath: $out,
            stageWidth: 480,
            stageHeight: 360,
            autoplay: false,
        ));

        $html = (string) file_get_contents($out);
        self::assertStringContainsString('if (false)', $html);
        self::assertStringContainsString("launchScreen.hidden = false", $html);
    }

    public function testInjectsBridgeAndWritesChecksum(): void
    {
        $out = $this->tmp('.html');
        $checksum = $this->tmp('.sha256');
        $projectPath = $this->sampleProject();

        (new HtmlPackager())->pack(new PackRequest(
            projectPath: $projectPath,
            outputPath: $out,
            stageWidth: 480,
            stageHeight: 360,
            customJs: BridgeScript::build(),
            countdownSeconds: 30,
            checksumFile: $checksum,
        ));

        $html = (string) file_get_contents($out);
        self::assertStringContainsString("type: 'scratch-broadcast'", $html); // bridge
        self::assertStringContainsString('const totalSeconds = 30;', $html);  // countdown
        self::assertFileExists($checksum);
        self::assertSame(hash('sha256', (string) file_get_contents($projectPath)) . "\n", file_get_contents($checksum));
    }

    public function testEscapesScriptCloserInCustomJs(): void
    {
        $out = $this->tmp('.html');
        $payload = "console.log('hostile string: </script><img src=x onerror=alert(1)>');";
        (new HtmlPackager())->pack(new PackRequest(
            projectPath: $this->sampleProject(),
            outputPath: $out,
            stageWidth: 480,
            stageHeight: 360,
            customJs: $payload,
        ));

        $html = (string) file_get_contents($out);
        self::assertStringNotContainsString('</script><img src=x', $html);
        self::assertStringContainsString('<\/script><img src=x', $html);
    }

    public function testEscapesScriptCloserInScaffoldingRuntime(): void
    {
        $stubRuntime = $this->tmp('-scaffolding.js');
        // Simulate minified JS that incidentally contains the literal "</script>"
        // inside a regex/string, which would otherwise break out of <script>.
        file_put_contents($stubRuntime, "var Scaffolding={Scaffolding:function(){this.bad='</script>'}};");

        $out = $this->tmp('.html');
        (new HtmlPackager($stubRuntime))->pack(new PackRequest(
            projectPath: $this->sampleProject(),
            outputPath: $out,
            stageWidth: 480,
            stageHeight: 360,
        ));

        $html = (string) file_get_contents($out);
        self::assertStringNotContainsString("this.bad='</script>'", $html);
        self::assertStringContainsString("this.bad='<\\/script>'", $html);
    }

    public function testMissingRuntimeThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing scaffolding runtime');
        (new HtmlPackager('/nonexistent/scaffolding.js'))->pack(new PackRequest(
            projectPath: $this->sampleProject(),
            outputPath: $this->tmp('.html'),
            stageWidth: 480,
            stageHeight: 360,
        ));
    }
}
