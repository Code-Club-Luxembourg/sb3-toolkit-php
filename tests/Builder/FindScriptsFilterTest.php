<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Builder;

use PHPUnit\Framework\TestCase;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Events;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Motion;
use CodeClubLuxembourg\Sb3Toolkit\Builder\BlockRef;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\Key;
use CodeClubLuxembourg\Sb3Toolkit\Builder\ScriptLocation;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Sprite;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Stage;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;

final class FindScriptsFilterTest extends TestCase
{
    private function makeMixedSprite(): Sprite
    {
        $project = ScratchProject::create();
        $sprite = $project->addSprite('Cat');
        $sprite->addScript(Events::whenFlagClicked()->then(Motion::moveSteps(10)));
        $sprite->addScript(Events::whenKeyPressed(Key::Space));
        $sprite->addScript(Events::whenKeyPressed(Key::Enter));
        $sprite->addScript(Events::whenSpriteClicked());

        return $sprite;
    }

    public function testFilterByStringOpcode(): void
    {
        $sprite = $this->makeMixedSprite();
        self::assertCount(1, $sprite->findScripts('event_whenflagclicked'));
        self::assertCount(2, $sprite->findScripts('event_whenkeypressed'));
    }

    public function testFilterByOpcodeList(): void
    {
        $sprite = $this->makeMixedSprite();
        $scripts = $sprite->findScripts(['event_whenflagclicked', 'event_whenspriteclicked']);
        self::assertCount(2, $scripts);
    }

    public function testFilterByPredicate(): void
    {
        $sprite = $this->makeMixedSprite();
        $spaceKey = $sprite->findScripts(
            static fn(BlockRef $hat): bool => $hat->opcode() === 'event_whenkeypressed'
                && $hat->getFieldValue('KEY_OPTION') === 'space'
        );
        self::assertCount(1, $spaceKey);
    }

    public function testFindAllScriptsCoversStageAndSprites(): void
    {
        $project = ScratchProject::create();
        $project->stage()->addScript(Events::whenFlagClicked());
        $project->addSprite('Cat')->addScript(Events::whenFlagClicked());
        $project->addSprite('Dog')->addScript(Events::whenKeyPressed(Key::Space));

        $locations = iterator_to_array($project->findAllScripts(), false);
        self::assertCount(3, $locations);

        $byTargetName = [];
        foreach ($locations as $loc) {
            self::assertInstanceOf(ScriptLocation::class, $loc);
            $byTargetName[$loc->target->name()][] = $loc->script->opcode();
        }
        self::assertSame(['event_whenflagclicked'], $byTargetName['Stage']);
        self::assertSame(['event_whenflagclicked'], $byTargetName['Cat']);
        self::assertSame(['event_whenkeypressed'], $byTargetName['Dog']);
    }

    public function testFindAllScriptsAcceptsFilter(): void
    {
        $project = ScratchProject::create();
        $project->stage()->addScript(Events::whenFlagClicked());
        $project->addSprite('Cat')->addScript(Events::whenKeyPressed(Key::Space));
        $project->addSprite('Dog')->addScript(Events::whenFlagClicked());

        $flag = iterator_to_array($project->findAllScripts('event_whenflagclicked'), false);
        self::assertCount(2, $flag);
        foreach ($flag as $loc) {
            self::assertContains($loc->target->name(), ['Stage', 'Dog']);
        }
    }

    public function testStageScriptsYieldStageInScriptLocation(): void
    {
        $project = ScratchProject::create();
        $project->stage()->addScript(Events::whenFlagClicked());

        $loc = iterator_to_array($project->findAllScripts(), false)[0];
        self::assertInstanceOf(Stage::class, $loc->target);
    }
}
