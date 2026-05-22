<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Builder;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Block;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\RotationStyle;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Sprite;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;

final class SpriteTest extends TestCase
{
    private function makeSprite(): Sprite
    {
        return ScratchProject::create()->addSprite('Cat');
    }

    public function testFindBlocksReturnsMatchingBlocks(): void
    {
        $sprite = $this->makeSprite();
        $sprite->addScript(
            Block::make('event_whenflagclicked')
                ->then(Block::make('motion_movesteps')->input('STEPS', 10))
                ->then(Block::make('motion_movesteps')->input('STEPS', 5)),
        );
        self::assertCount(2, $sprite->findBlocks('motion_movesteps'));
    }

    public function testFindScriptsReturnsOnlyTopLevel(): void
    {
        $sprite = $this->makeSprite();
        $sprite->addScript(Block::make('event_whenflagclicked')->then(Block::make('motion_movesteps')->input('STEPS', 10)));
        $sprite->addScript(Block::make('event_whenkeypressed')->field('KEY_OPTION', 'space'));
        self::assertCount(2, $sprite->findScripts());
    }

    public function testFindScriptsFiltersByHatOpcode(): void
    {
        $sprite = $this->makeSprite();
        $sprite->addScript(Block::make('event_whenflagclicked')->then(Block::make('motion_movesteps')->input('STEPS', 10)));
        $sprite->addScript(Block::make('event_whenkeypressed')->field('KEY_OPTION', 'space'));
        $scripts = $sprite->findScripts('event_whenflagclicked');
        self::assertCount(1, $scripts);
        self::assertSame('event_whenflagclicked', $scripts[0]->data()['opcode']);
    }

    public function testBlockReturnsRef(): void
    {
        $sprite = $this->makeSprite();
        $hat = Block::make('event_whenflagclicked');
        $sprite->addScript($hat);
        self::assertSame('event_whenflagclicked', $sprite->block($hat->id)->data()['opcode']);
    }

    public function testBlockThrowsForMissingBlock(): void
    {
        $this->expectException(RuntimeException::class);
        $this->makeSprite()->block('nonexistent');
    }

    public function testInputMutatesBlockInPlace(): void
    {
        $sprite = $this->makeSprite();
        $move = Block::make('motion_movesteps')->input('STEPS', 10);
        $sprite->addScript(Block::make('event_whenflagclicked')->then($move));
        $sprite->block($move->id)->input('STEPS', 99);
        self::assertSame([1, [4, 99]], $sprite->data()['blocks'][$move->id]['inputs']['STEPS']);
    }

    public function testFieldMutatesFieldInPlace(): void
    {
        $sprite = $this->makeSprite();
        $stop = Block::make('control_stop')->field('STOP_OPTION', 'all');
        $sprite->addScript(Block::make('event_whenflagclicked')->then($stop));
        $sprite->block($stop->id)->field('STOP_OPTION', 'this script');
        self::assertSame(['this script', null], $sprite->data()['blocks'][$stop->id]['fields']['STOP_OPTION']);
    }

    public function testInsertAfterRelinksTheChain(): void
    {
        $sprite = $this->makeSprite();
        $hat = Block::make('event_whenflagclicked');
        $moveA = Block::make('motion_movesteps')->input('STEPS', 10);
        $moveB = Block::make('motion_movesteps')->input('STEPS', 20);
        $hat->then($moveA);
        $sprite->addScript($hat);
        $sprite->block($hat->id)->insertAfter($moveB);

        $blocks = $sprite->data()['blocks'];
        self::assertSame($moveB->id, $blocks[$hat->id]['next']);
        self::assertSame($moveA->id, $blocks[$moveB->id]['next']);
        self::assertSame($moveB->id, $blocks[$moveA->id]['parent']);
    }

    public function testDeleteRemovesBlockAndRelinksChain(): void
    {
        $sprite = $this->makeSprite();
        $hat = Block::make('event_whenflagclicked');
        $moveA = Block::make('motion_movesteps')->input('STEPS', 10);
        $moveB = Block::make('motion_movesteps')->input('STEPS', 20);
        $hat->then($moveA)->then($moveB);
        $sprite->addScript($hat);
        $sprite->block($moveA->id)->delete();

        $blocks = $sprite->data()['blocks'];
        self::assertSame($moveB->id, $blocks[$hat->id]['next']);
        self::assertSame($hat->id, $blocks[$moveB->id]['parent']);
        self::assertArrayNotHasKey($moveA->id, $blocks);
    }

    public function testInputOverwriteFreesPreviousNestedBlocks(): void
    {
        $sprite = $this->makeSprite();
        $hat = Block::make('event_whenflagclicked');
        $move = Block::make('motion_movesteps');
        $reporter = Block::make('operator_add')
            ->input('NUM1', 1)
            ->input('NUM2', Block::make('operator_multiply')->input('NUM1', 2)->input('NUM2', 3));
        $move->input('STEPS', $reporter);
        $hat->then($move);
        $sprite->addScript($hat);

        $blocksBefore = $sprite->data()['blocks'];
        self::assertArrayHasKey($reporter->id, $blocksBefore);

        $sprite->block($move->id)->input('STEPS', 7);

        $blocksAfter = $sprite->data()['blocks'];
        self::assertArrayNotHasKey($reporter->id, $blocksAfter);
        foreach ($blocksBefore as $id => $b) {
            if (\is_array($b) && ($b['parent'] ?? null) === $reporter->id) {
                self::assertArrayNotHasKey($id, $blocksAfter);
            }
        }
        self::assertSame([1, [4, 7]], $blocksAfter[$move->id]['inputs']['STEPS']);
    }

    public function testDeleteFreesNestedInputSubtree(): void
    {
        $sprite = $this->makeSprite();
        $hat = Block::make('event_whenflagclicked');
        $reporter = Block::make('operator_add')->input('NUM1', 1)->input('NUM2', 2);
        $move = Block::make('motion_movesteps')->input('STEPS', $reporter);
        $hat->then($move);
        $sprite->addScript($hat);

        self::assertArrayHasKey($reporter->id, $sprite->data()['blocks']);

        $sprite->block($move->id)->delete();
        $blocks = $sprite->data()['blocks'];
        self::assertArrayNotHasKey($move->id, $blocks);
        self::assertArrayNotHasKey($reporter->id, $blocks);
    }

    public function testDeleteFreesSubstackBlocks(): void
    {
        $sprite = $this->makeSprite();
        $hat = Block::make('event_whenflagclicked');
        $body = Block::make('motion_movesteps')->input('STEPS', 10);
        $loop = Block::make('control_forever')->input('SUBSTACK', Block::stack($body));
        $hat->then($loop);
        $sprite->addScript($hat);

        self::assertArrayHasKey($body->id, $sprite->data()['blocks']);

        $sprite->block($loop->id)->delete();
        $blocks = $sprite->data()['blocks'];
        self::assertArrayNotHasKey($loop->id, $blocks);
        self::assertArrayNotHasKey($body->id, $blocks);
    }

    public function testDeleteHeadOfScriptPromotesNextToTopLevel(): void
    {
        $sprite = $this->makeSprite();
        $hat = Block::make('event_whenflagclicked');
        $move = Block::make('motion_movesteps')->input('STEPS', 10);
        $hat->then($move);
        $sprite->addScript($hat, 12, 34);

        $sprite->block($hat->id)->delete();
        $blocks = $sprite->data()['blocks'];
        self::assertArrayNotHasKey($hat->id, $blocks);
        self::assertTrue($blocks[$move->id]['topLevel']);
        self::assertNull($blocks[$move->id]['parent']);
        self::assertSame(12, $blocks[$move->id]['x']);
        self::assertSame(34, $blocks[$move->id]['y']);
    }

    public function testTypedAccessorsReadInputsAndFields(): void
    {
        $sprite = $this->makeSprite();
        $hat = Block::make('event_whenflagclicked');
        $move = Block::make('motion_movesteps')->input('STEPS', 17);
        $stop = Block::make('control_stop')->field('STOP_OPTION', 'all');
        $hat->then($move)->then($stop);
        $sprite->addScript($hat);

        $moveRef = $sprite->block($move->id);
        self::assertSame('motion_movesteps', $moveRef->opcode());
        self::assertSame([1, [4, 17]], $moveRef->getInputSlot('STEPS'));
        self::assertSame(17, $moveRef->getInputLiteralValue('STEPS'));
        self::assertNull($moveRef->getInputSlot('DOES_NOT_EXIST'));
        self::assertNull($moveRef->getInputLiteralValue('DOES_NOT_EXIST'));

        $stopRef = $sprite->block($stop->id);
        self::assertSame(['all', null], $stopRef->getField('STOP_OPTION'));
        self::assertSame('all', $stopRef->getFieldValue('STOP_OPTION'));
        self::assertNull($stopRef->getFieldValue('NOT_SET'));
    }

    public function testInputLiteralValueIsNullForBlockReference(): void
    {
        $sprite = $this->makeSprite();
        $hat = Block::make('event_whenflagclicked');
        $reporter = Block::make('motion_xposition');
        $move = Block::make('motion_movesteps')->input('STEPS', $reporter);
        $hat->then($move);
        $sprite->addScript($hat);

        $ref = $sprite->block($move->id);
        $slot = $ref->getInputSlot('STEPS');
        self::assertNotNull($slot);
        self::assertSame(2, $slot[0]); // NO_SHADOW: block reference
        self::assertNull($ref->getInputLiteralValue('STEPS'));
    }

    public function testSettersMutateSpriteTarget(): void
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite('Cat');
        $cat->addCostume('extra', '<svg/>');

        $cat
            ->setCurrentCostume(1)
            ->setPosition(40, -10)
            ->setSize(75)
            ->setDirection(45)
            ->setVisible(false)
            ->setDraggable(true)
            ->setRotationStyle(RotationStyle::LeftRight)
            ->setVolume(80);

        $data = $cat->data();
        self::assertSame(1, $data['currentCostume']);
        self::assertSame(40, $data['x']);
        self::assertSame(-10, $data['y']);
        self::assertSame(75, $data['size']);
        self::assertSame(45, $data['direction']);
        self::assertFalse($data['visible']);
        self::assertTrue($data['draggable']);
        self::assertSame('left-right', $data['rotationStyle']);
        self::assertSame(80, $data['volume']);
    }

    public function testSetCurrentCostumeRejectsOutOfRange(): void
    {
        $sprite = $this->makeSprite();
        $this->expectException(RuntimeException::class);
        $sprite->setCurrentCostume(5);
    }

    public function testAddVariableStoresVariableAndReturnsRef(): void
    {
        $sprite = $this->makeSprite();
        $ref = $sprite->addVariable('score', 0);
        self::assertSame('score', $ref->name);
        self::assertSame(['score', 0], $sprite->data()['variables'][$ref->id]);
    }

    public function testAddListStoresListAndReturnsRef(): void
    {
        $sprite = $this->makeSprite();
        $ref = $sprite->addList('items');
        self::assertSame('items', $ref->name);
        self::assertSame(['items', []], $sprite->data()['lists'][$ref->id]);
    }

    public function testRemoveCostumeDropsBlankPlaceholder(): void
    {
        $sprite = $this->makeSprite();
        $sprite->addCostume('wizard', '<svg/>');
        $sprite->removeCostume(0); // drop the auto-created blank costume

        $costumes = array_values($sprite->data()['costumes']);
        self::assertCount(1, $costumes);
        self::assertSame('wizard', $costumes[0]['name']);
    }

    public function testRemoveCostumeAdjustsCurrentCostume(): void
    {
        $sprite = $this->makeSprite();
        $sprite->addCostume('wizard', '<svg/>');
        $sprite->setCurrentCostume(1);
        $sprite->removeCostume(0); // current shifts from 1 down to 0

        self::assertSame(0, $sprite->data()['currentCostume']);
    }
}
