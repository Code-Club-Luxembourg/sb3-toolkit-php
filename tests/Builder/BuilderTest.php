<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Builder;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Block;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Events;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Looks;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Motion;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Id;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Input;
use CodeClubLuxembourg\Sb3Toolkit\Builder\VariableRef;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ScratchValidator;

final class BuilderTest extends TestCase
{
    public function testGenerateIdReturnsLongStrings(): void
    {
        self::assertGreaterThanOrEqual(16, \strlen(Id::generate()));
    }

    public function testGenerateIdProducesUniqueIds(): void
    {
        $ids = [];
        for ($i = 0; $i < 10_000; ++$i) {
            $ids[Id::generate()] = true;
        }
        self::assertCount(10_000, $ids);
    }

    public function testBlockFactoryCreatesCorrectOpcode(): void
    {
        $b = Block::make('event_whenflagclicked');
        $blocks = $b->toBlocks(10, 20);
        $entry = $blocks[$b->id];
        self::assertSame('event_whenflagclicked', $entry['opcode']);
        self::assertTrue($entry['topLevel']);
        self::assertSame(10, $entry['x']);
        self::assertSame(20, $entry['y']);
        self::assertNull($entry['next']);
        self::assertNull($entry['parent']);
    }

    public function testEncodesNumberInputAsShadow(): void
    {
        $b = Block::make('motion_movesteps')->input('STEPS', 10);
        $blocks = $b->toBlocks();
        self::assertSame([1, [4, 10]], $blocks[$b->id]['inputs']['STEPS']);
    }

    public function testEncodesStringInputAsShadow(): void
    {
        $b = Block::make('looks_say')->input('MESSAGE', 'Hello!');
        $blocks = $b->toBlocks();
        self::assertSame([1, [10, 'Hello!']], $blocks[$b->id]['inputs']['MESSAGE']);
    }

    public function testEncodesBlockInputAsNoShadowReference(): void
    {
        $inner = Block::make('motion_xposition');
        $outer = Block::make('looks_say')->input('MESSAGE', $inner);
        $blocks = $outer->toBlocks();
        self::assertSame([2, $inner->id], $blocks[$outer->id]['inputs']['MESSAGE']);
        self::assertSame($outer->id, $blocks[$inner->id]['parent']);
    }

    public function testEncodesTypedInputValueObject(): void
    {
        $b = Block::make('data_setvariableto')->input('VALUE', Input::variable(new VariableRef('score', 'var-id')));
        $blocks = $b->toBlocks();
        self::assertSame([1, [12, 'score', 'var-id']], $blocks[$b->id]['inputs']['VALUE']);
    }

    public function testEncodesFields(): void
    {
        $b = Block::make('data_setvariableto')->input('VALUE', 0)->field('VARIABLE', 'score', 'var-id');
        $blocks = $b->toBlocks();
        self::assertSame(['score', 'var-id'], $blocks[$b->id]['fields']['VARIABLE']);
    }

    public function testEncodesFieldWithoutId(): void
    {
        $b = Block::make('control_stop')->field('STOP_OPTION', 'all');
        $blocks = $b->toBlocks();
        self::assertSame(['all', null], $blocks[$b->id]['fields']['STOP_OPTION']);
    }

    public function testThenReturnsTheRootBlock(): void
    {
        $hat = Block::make('event_whenflagclicked');
        $move = Block::make('motion_movesteps')->input('STEPS', 10);
        self::assertSame($hat, $hat->then($move));
    }

    public function testAndThenReturnsTheAppendedBlock(): void
    {
        $hat = Events::whenFlagClicked();
        $move = Motion::moveSteps(10);
        self::assertSame($move, $hat->andThen($move));
    }

    public function testAndThenWalksForwardOnEachCall(): void
    {
        $hat = Events::whenFlagClicked();
        $a = Motion::moveSteps(1);
        $b = Motion::moveSteps(2);
        $c = Looks::say('done');

        // andThen returns the new tail, so chaining keeps moving forward.
        $hat->andThen($a)->andThen($b)->andThen($c);

        $blocks = $hat->toBlocks();
        self::assertSame($a->id, $blocks[$hat->id]['next']);
        self::assertSame($b->id, $blocks[$a->id]['next']);
        self::assertSame($c->id, $blocks[$b->id]['next']);
        self::assertNull($blocks[$c->id]['next']);
    }

    public function testThenChainsThreeBlocksAnchoredOnRoot(): void
    {
        $a = Block::make('event_whenflagclicked');
        $b = Block::make('motion_movesteps')->input('STEPS', 5);
        $c = Block::make('motion_movesteps')->input('STEPS', 5);
        $a->then($b)->then($c);
        $blocks = $a->toBlocks();
        self::assertCount(3, $blocks);
        self::assertSame($b->id, $blocks[$a->id]['next']);
        self::assertSame($c->id, $blocks[$b->id]['next']);
        self::assertNull($blocks[$a->id]['parent']);
        self::assertSame($a->id, $blocks[$b->id]['parent']);
        self::assertSame($b->id, $blocks[$c->id]['parent']);
        self::assertNull($blocks[$c->id]['next']);
    }

    public function testCreateProducesValidProject(): void
    {
        self::assertTrue(ScratchValidator::validate(ScratchProject::create())->isValid);
    }

    public function testCreateHasStageAndNoSprites(): void
    {
        $project = ScratchProject::create();
        self::assertCount(0, $project->listSprites());
        $project->getStage();
    }

    public function testAddSpriteAddsSprite(): void
    {
        $project = ScratchProject::create();
        $project->addSprite('Cat');
        self::assertCount(1, $project->listSprites());
        self::assertSame('Cat', $project->listSprites()[0]['name']);
    }

    public function testAddSpriteIncrementsLayerOrder(): void
    {
        $project = ScratchProject::create();
        $project->addSprite('Cat');
        $project->addSprite('Dog');
        $byName = [];
        foreach ($project->listSprites() as $sprite) {
            $byName[$sprite['name']] = $sprite['layerOrder'];
        }
        self::assertSame(1, $byName['Cat']);
        self::assertSame(2, $byName['Dog']);
    }

    public function testSpriteThrowsForUnknownName(): void
    {
        $this->expectException(RuntimeException::class);
        ScratchProject::create()->sprite('Nonexistent');
    }

    public function testSpriteCanAddScripts(): void
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite('Cat');
        $cat->addScript(Block::make('event_whenflagclicked')->then(Block::make('motion_movesteps')->input('STEPS', 10)));

        $sprite = $project->listSprites()[0];
        self::assertSame(2, \count(array_filter($sprite['blocks'])));
    }

    public function testProjectBuiltWithBuilderPassesValidation(): void
    {
        $project = ScratchProject::create();
        $project->addSprite('Cat')->addScript(
            Block::make('event_whenflagclicked')
                ->then(Block::make('motion_movesteps')->input('STEPS', 10))
                ->then(Block::make('looks_say')->input('MESSAGE', 'Hello!')),
        );
        self::assertTrue(ScratchValidator::validate($project)->isValid);
    }
}
