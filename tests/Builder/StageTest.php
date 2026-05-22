<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Builder;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Events;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Looks;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Stage;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;

final class StageTest extends TestCase
{
    public function testStageReturnsLiveTarget(): void
    {
        $project = ScratchProject::create();
        $stage = $project->stage();
        self::assertInstanceOf(Stage::class, $stage);
        self::assertSame('Stage', $stage->name());
    }

    public function testStageCanAddScripts(): void
    {
        $project = ScratchProject::create();
        $stage = $project->stage();
        $hat = Events::whenFlagClicked()->then(Looks::switchBackdropTo('next backdrop'));
        $stage->addScript($hat);

        $blocks = $stage->data()['blocks'];
        self::assertArrayHasKey($hat->id, $blocks);
        self::assertTrue($blocks[$hat->id]['topLevel']);
    }

    public function testStageFindScriptsReturnsHats(): void
    {
        $project = ScratchProject::create();
        $stage = $project->stage();
        $stage->addScript(Events::whenFlagClicked());
        $stage->addScript(Events::whenBackdropSwitchesTo('backdrop1'));

        self::assertCount(2, $stage->findScripts());
        self::assertCount(1, $stage->findScripts('event_whenflagclicked'));
    }

    public function testStageAddBackdropAndSetCurrentBackdrop(): void
    {
        $project = ScratchProject::create();
        $stage = $project->stage();
        $idx = $stage->addBackdrop('extra', '<svg/>');
        $stage->setCurrentBackdrop($idx);
        self::assertSame($idx, $stage->data()['currentCostume']);
    }

    public function testStageSetCurrentBackdropRejectsOutOfRange(): void
    {
        $project = ScratchProject::create();
        $this->expectException(RuntimeException::class);
        $project->stage()->setCurrentBackdrop(99);
    }

    public function testStageAddVariableIsGlobal(): void
    {
        $project = ScratchProject::create();
        $stage = $project->stage();
        $ref = $stage->addVariable('score', 0);

        // The variable lands on the stage target — sprites can reference it.
        self::assertArrayHasKey($ref->id, $project->getStage()['variables']);
    }
}
