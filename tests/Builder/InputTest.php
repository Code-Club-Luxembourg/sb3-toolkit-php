<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Builder;

use PHPUnit\Framework\TestCase;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Block;
use CodeClubLuxembourg\Sb3Toolkit\Builder\BroadcastRef;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Input;
use CodeClubLuxembourg\Sb3Toolkit\Builder\ListRef;
use CodeClubLuxembourg\Sb3Toolkit\Builder\VariableRef;

final class InputTest extends TestCase
{
    public function testNumberEncoding(): void
    {
        self::assertSame([1, [4, 10]], Input::number(10)->toSlot());
        self::assertSame([1, [4, '42']], Input::number('42')->toSlot());
    }

    public function testTextEncoding(): void
    {
        self::assertSame([1, [10, 'hello']], Input::text('hello')->toSlot());
    }

    public function testReporterEncodings(): void
    {
        self::assertSame([1, [12, 'score', 'var-id']], Input::variable(new VariableRef('score', 'var-id'))->toSlot());
        self::assertSame([1, [13, 'myList', 'list-id']], Input::list(new ListRef('myList', 'list-id'))->toSlot());
        self::assertSame([1, [11, 'start', 'bc-id']], Input::broadcast(new BroadcastRef('start', 'bc-id'))->toSlot());
        self::assertSame([1, [9, '#ff0000']], Input::color('#ff0000')->toSlot());
    }

    public function testBlockEncoding(): void
    {
        $child = Block::make('motion_xposition');
        $input = Input::block($child);
        self::assertSame([2, $child->id], $input->toSlot());
        self::assertSame([$child], $input->childBlocks());
    }

    public function testMenuWithStringEncodesAsShadow(): void
    {
        $input = Input::menu('motion_glideto_menu', 'TO', '_random_');
        $slot = $input->toSlot();
        self::assertSame(Input::SHADOW, $slot[0]);
        self::assertIsString($slot[1]);
        self::assertCount(1, $input->childBlocks());
    }

    public function testMenuWithBlockEncodesAsBlockAndShadow(): void
    {
        $reporter = Block::make('operator_join');
        $input = Input::menu('motion_glideto_menu', 'TO', $reporter);
        $slot = $input->toSlot();
        self::assertSame(Input::BLOCK_AND_SHADOW, $slot[0]);
        self::assertSame($reporter->id, $slot[1]);
        self::assertIsString($slot[2]);
        self::assertNotSame($reporter->id, $slot[2]);

        $children = $input->childBlocks();
        self::assertCount(2, $children);
        self::assertSame($reporter, $children[0]);
    }

    public function testWrapNormalizesLooseValues(): void
    {
        self::assertSame([1, [4, 7]], Input::wrap(7)->toSlot());
        self::assertSame([1, [4, 1.5]], Input::wrap(1.5)->toSlot());
        self::assertSame([1, [10, 'hi']], Input::wrap('hi')->toSlot());

        $child = Block::make('motion_xposition');
        self::assertSame([2, $child->id], Input::wrap($child)->toSlot());
    }

    public function testWrapReturnsSameInstanceForInput(): void
    {
        $input = Input::number(3);
        self::assertSame($input, Input::wrap($input));
    }

    public function testLiteralInputHasNoChildBlocks(): void
    {
        self::assertSame([], Input::number(1)->childBlocks());
    }
}
