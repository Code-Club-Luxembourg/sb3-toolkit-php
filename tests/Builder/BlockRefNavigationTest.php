<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Builder;

use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\Key;
use PHPUnit\Framework\TestCase;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Block;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Control;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Events;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Looks;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Motion;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Operators;
use CodeClubLuxembourg\Sb3Toolkit\Builder\BlockRef;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;

final class BlockRefNavigationTest extends TestCase
{
    private function spriteWithChain(): array
    {
        $project = ScratchProject::create();
        $sprite = $project->addSprite('Cat');
        $hat = Events::whenFlagClicked();
        $a = Motion::moveSteps(10);
        $b = Motion::moveSteps(20);
        $c = Looks::say('done');
        $hat->then($a)->then($b)->then($c);
        $sprite->addScript($hat);

        return ['sprite' => $sprite, 'hat' => $hat, 'a' => $a, 'b' => $b, 'c' => $c];
    }

    public function testNextWalksTheChain(): void
    {
        ['sprite' => $sprite, 'hat' => $hat, 'a' => $a] = $this->spriteWithChain();
        $hatRef = $sprite->block($hat->id);

        $next = $hatRef->next();
        self::assertNotNull($next);
        self::assertSame($a->id, $next->id);
    }

    public function testNextReturnsNullForTail(): void
    {
        ['sprite' => $sprite, 'c' => $c] = $this->spriteWithChain();
        self::assertNull($sprite->block($c->id)->next());
    }

    public function testParentReturnsChainPredecessor(): void
    {
        ['sprite' => $sprite, 'hat' => $hat, 'a' => $a] = $this->spriteWithChain();
        $parent = $sprite->block($a->id)->parent();
        self::assertNotNull($parent);
        self::assertSame($hat->id, $parent->id);
    }

    public function testPreviousReturnsChainPredecessor(): void
    {
        ['sprite' => $sprite, 'hat' => $hat, 'b' => $b, 'a' => $a] = $this->spriteWithChain();
        $prev = $sprite->block($b->id)->previous();
        self::assertNotNull($prev);
        self::assertSame($a->id, $prev->id);
        self::assertNull($sprite->block($hat->id)->previous());
    }

    public function testPreviousReturnsNullForInputChild(): void
    {
        // STEPS is set to a reporter — the reporter's parent is the motion block,
        // but it is not the chain predecessor.
        $project = ScratchProject::create();
        $sprite = $project->addSprite('Cat');
        $reporter = Block::make('motion_xposition');
        $move = Motion::moveSteps($reporter);
        $sprite->addScript(Events::whenFlagClicked()->then($move));

        $reporterRef = $sprite->block($reporter->id);
        self::assertNotNull($reporterRef->parent());
        self::assertNull($reporterRef->previous());
    }

    public function testGetInputBlockReachesReportersAndSubstacks(): void
    {
        $project = ScratchProject::create();
        $sprite = $project->addSprite('Cat');
        $body = Motion::moveSteps(10);
        $reporter = Block::make('motion_xposition');
        $forever = Control::forever([$body]);
        $movement = Block::make('motion_movesteps')->input('STEPS', $reporter);
        $sprite->addScript(Events::whenFlagClicked()->then($movement)->then($forever));

        $reporterRef = $sprite->block($movement->id)->getInputBlock('STEPS');
        self::assertNotNull($reporterRef);
        self::assertSame($reporter->id, $reporterRef->id);

        $bodyRef = $sprite->block($forever->id)->getInputBlock('SUBSTACK');
        self::assertNotNull($bodyRef);
        self::assertSame($body->id, $bodyRef->id);

        self::assertNull($sprite->block($movement->id)->getInputBlock('DOES_NOT_EXIST'));
    }

    public function testChainYieldsEveryStatement(): void
    {
        ['sprite' => $sprite, 'hat' => $hat, 'a' => $a, 'b' => $b, 'c' => $c] = $this->spriteWithChain();
        $ids = array_map(static fn(BlockRef $r): string => $r->id, iterator_to_array($sprite->block($hat->id)->chain(), false));
        self::assertSame([$hat->id, $a->id, $b->id, $c->id], $ids);
    }

    public function testChainFromMidScriptStartsThere(): void
    {
        ['sprite' => $sprite, 'b' => $b, 'c' => $c] = $this->spriteWithChain();
        $ids = array_map(static fn(BlockRef $r): string => $r->id, iterator_to_array($sprite->block($b->id)->chain(), false));
        self::assertSame([$b->id, $c->id], $ids);
    }

    public function testSubstackYieldsBodyBlocks(): void
    {
        $project = ScratchProject::create();
        $sprite = $project->addSprite('Cat');
        $b1 = Motion::moveSteps(1);
        $b2 = Motion::moveSteps(2);
        $loop = Control::forever([$b1, $b2]);
        $sprite->addScript(Events::whenFlagClicked()->then($loop));

        $ids = array_map(static fn(BlockRef $r): string => $r->id, iterator_to_array($sprite->block($loop->id)->substack(), false));
        self::assertSame([$b1->id, $b2->id], $ids);
    }

    public function testSubstackYieldsNothingForEmptyOrAbsentSlot(): void
    {
        $project = ScratchProject::create();
        $sprite = $project->addSprite('Cat');
        $hat = Events::whenFlagClicked();
        $sprite->addScript($hat);
        $items = iterator_to_array($sprite->block($hat->id)->substack(), false);
        self::assertSame([], $items);
    }

    public function testDescendantsCoversChainAndReportersAndSubstacks(): void
    {
        $project = ScratchProject::create();
        $sprite = $project->addSprite('Cat');
        $reporter = Operators::add(1, 2);
        $body = Motion::moveSteps($reporter);
        $loop = Control::repeat(3, [$body]);
        $tail = Looks::say('done');
        $hat = Events::whenFlagClicked()->then($loop)->then($tail);
        $sprite->addScript($hat);

        $ids = array_map(static fn(BlockRef $r): string => $r->id, iterator_to_array($sprite->block($hat->id)->descendants(), false));
        // Hat, loop (+ reporter from TIMES would only be there if it were a block — 3 is a literal),
        // body, reporter, tail — exact order is DFS over inputs then next.
        self::assertContains($hat->id, $ids);
        self::assertContains($loop->id, $ids);
        self::assertContains($body->id, $ids);
        self::assertContains($reporter->id, $ids);
        self::assertContains($tail->id, $ids);
    }

    public function testInsertBeforeOnTopLevelPromotesNewHead(): void
    {
        ['sprite' => $sprite, 'hat' => $hat] = $this->spriteWithChain();
        $newHat = Events::whenKeyPressed(Key::Space);
        $sprite->block($hat->id)->insertBefore($newHat);

        $blocks = $sprite->data()['blocks'];
        self::assertTrue($blocks[$newHat->id]['topLevel']);
        self::assertSame(0, $blocks[$newHat->id]['x']);
        self::assertSame(0, $blocks[$newHat->id]['y']);
        self::assertSame($hat->id, $blocks[$newHat->id]['next']);
        self::assertFalse($blocks[$hat->id]['topLevel']);
        self::assertSame($newHat->id, $blocks[$hat->id]['parent']);
        self::assertArrayNotHasKey('x', $blocks[$hat->id]);
        self::assertArrayNotHasKey('y', $blocks[$hat->id]);
    }

    public function testInsertBeforeMidChainRelinksParentNext(): void
    {
        ['sprite' => $sprite, 'a' => $a, 'b' => $b, 'hat' => $hat] = $this->spriteWithChain();
        $inserted = Motion::moveSteps(99);
        $sprite->block($b->id)->insertBefore($inserted);

        $blocks = $sprite->data()['blocks'];
        // hat → a → inserted → b → c
        self::assertSame($inserted->id, $blocks[$a->id]['next']);
        self::assertSame($a->id, $blocks[$inserted->id]['parent']);
        self::assertSame($b->id, $blocks[$inserted->id]['next']);
        self::assertSame($inserted->id, $blocks[$b->id]['parent']);
    }

    public function testReplacePreservesParentAndNext(): void
    {
        ['sprite' => $sprite, 'a' => $a, 'b' => $b, 'hat' => $hat] = $this->spriteWithChain();
        $replacement = Looks::say('changed');
        $sprite->block($a->id)->replace($replacement);

        $blocks = $sprite->data()['blocks'];
        self::assertArrayNotHasKey($a->id, $blocks);
        self::assertSame($replacement->id, $blocks[$hat->id]['next']);
        self::assertSame($hat->id, $blocks[$replacement->id]['parent']);
        self::assertSame($b->id, $blocks[$replacement->id]['next']);
        self::assertSame($replacement->id, $blocks[$b->id]['parent']);
    }

    public function testReplaceOnTopLevelPreservesCoords(): void
    {
        $project = ScratchProject::create();
        $sprite = $project->addSprite('Cat');
        $hat = Events::whenFlagClicked();
        $sprite->addScript($hat, 100, 200);

        $newHat = Events::whenKeyPressed(Key::Space);
        $sprite->block($hat->id)->replace($newHat);

        $blocks = $sprite->data()['blocks'];
        self::assertArrayNotHasKey($hat->id, $blocks);
        self::assertTrue($blocks[$newHat->id]['topLevel']);
        self::assertSame(100, $blocks[$newHat->id]['x']);
        self::assertSame(200, $blocks[$newHat->id]['y']);
    }

    public function testRemoveScriptDropsHatAndWholeChain(): void
    {
        ['sprite' => $sprite, 'hat' => $hat, 'a' => $a, 'b' => $b, 'c' => $c] = $this->spriteWithChain();
        $sprite->block($hat->id)->removeScript();

        $blocks = $sprite->data()['blocks'];
        self::assertArrayNotHasKey($hat->id, $blocks);
        self::assertArrayNotHasKey($a->id, $blocks);
        self::assertArrayNotHasKey($b->id, $blocks);
        self::assertArrayNotHasKey($c->id, $blocks);
    }

    public function testRemoveScriptOnMidBlockKeepsPredecessors(): void
    {
        ['sprite' => $sprite, 'hat' => $hat, 'a' => $a, 'b' => $b, 'c' => $c] = $this->spriteWithChain();
        $sprite->block($b->id)->removeScript();

        $blocks = $sprite->data()['blocks'];
        self::assertArrayHasKey($hat->id, $blocks);
        self::assertArrayHasKey($a->id, $blocks);
        self::assertArrayNotHasKey($b->id, $blocks);
        self::assertArrayNotHasKey($c->id, $blocks);
        self::assertNull($blocks[$a->id]['next']);
    }
}
