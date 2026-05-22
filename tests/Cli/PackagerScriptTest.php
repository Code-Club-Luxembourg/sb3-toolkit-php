<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Cli;

use PHPUnit\Framework\TestCase;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Packager\BridgeScript;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Packager\CountdownScript;

final class PackagerScriptTest extends TestCase
{
    public function testBridgeScriptForwardsBroadcasts(): void
    {
        $script = BridgeScript::build();
        self::assertStringContainsString('event_whenbroadcastreceived', $script);
        self::assertStringContainsString("type: 'scratch-broadcast'", $script);
        self::assertStringContainsString('vm.runtime.startHats', $script);
    }

    public function testCountdownScriptEmbedsOptions(): void
    {
        $script = CountdownScript::build(60, 'out.html.sha256', 'abc123');
        self::assertStringContainsString('const totalSeconds = 60;', $script);
        self::assertStringContainsString('const checksumUrl = "out.html.sha256";', $script);
        self::assertStringContainsString('const initialChecksum = "abc123";', $script);
    }
}
