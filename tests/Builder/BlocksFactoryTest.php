<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Builder;

use PHPUnit\Framework\TestCase;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Block;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Control;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Data;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Events;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Looks;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Motion;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Operators;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Sensing;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\Key;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\StopOption;
use CodeClubLuxembourg\Sb3Toolkit\Builder\VariableRef;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ErrorCode;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ScratchValidator;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationError;

final class BlocksFactoryTest extends TestCase
{
    public function testNumberInputFactoryMatchesManualChain(): void
    {
        $factory = Motion::moveSteps(10);
        $manual = Block::make('motion_movesteps')->input('STEPS', 10);
        self::assertSame(
            $this->entry($manual)['inputs'],
            $this->entry($factory)['inputs'],
        );
        self::assertSame('motion_movesteps', $this->entry($factory)['opcode']);
    }

    public function testTextInputFactory(): void
    {
        $b = Looks::say('Hi!');
        self::assertSame([1, [10, 'Hi!']], $this->entry($b)['inputs']['MESSAGE']);
    }

    public function testColorInputFactoryWrapsHexString(): void
    {
        $b = Sensing::touchingColor('#ff0000');
        self::assertSame([1, [9, '#ff0000']], $this->entry($b)['inputs']['COLOR']);
    }

    public function testBooleanInputFactoryTakesReporterBlock(): void
    {
        $b = Operators::not(Sensing::touchingColor('#000000'));
        $slot = $this->entry($b)['inputs']['OPERAND'];
        self::assertSame(2, $slot[0]); // no-shadow block reference
    }

    public function testEnumFieldFactory(): void
    {
        $b = Control::stop(StopOption::OtherScriptsInSprite);
        self::assertSame(['other scripts in sprite', null], $this->entry($b)['fields']['STOP_OPTION']);
    }

    public function testIdBearingFieldFactory(): void
    {
        $b = Data::setVariableTo(5, new VariableRef('score', 'var-id'));
        self::assertSame([1, [4, 5]], $this->entry($b)['inputs']['VALUE']);
        self::assertSame(['score', 'var-id'], $this->entry($b)['fields']['VARIABLE']);
    }

    public function testArraySubstackChainsBody(): void
    {
        $forever = Control::forever([
            Motion::moveSteps(2),
            Motion::turnRight(15),
        ]);
        $blocks = $forever->toBlocks();

        $substack = $blocks[$forever->id]['inputs']['SUBSTACK'];
        self::assertSame(2, $substack[0]); // no-shadow block reference
        $headId = $substack[1];
        self::assertSame('motion_movesteps', $blocks[$headId]['opcode']);
        // body head chains to the second block
        self::assertSame('motion_turnright', $blocks[$blocks[$headId]['next']]['opcode']);
    }

    public function testIfElseFillsBothBranches(): void
    {
        $block = Control::ifElse(
            Operators::equals(1, 1),
            [Motion::moveSteps(1)],
            [Motion::moveSteps(-1)],
        );
        $entry = $this->entry($block);
        self::assertArrayHasKey('SUBSTACK', $entry['inputs']);
        self::assertArrayHasKey('SUBSTACK2', $entry['inputs']);
        self::assertArrayHasKey('CONDITION', $entry['inputs']);
    }

    public function testKeyEnumIsAcceptedByWhenKeyPressed(): void
    {
        $block = Events::whenKeyPressed(Key::Space);
        self::assertSame('event_whenkeypressed', $this->entry($block)['opcode']);
        self::assertSame(['space', null], $this->entry($block)['fields']['KEY_OPTION']);
    }

    public function testKeyEnumIsAcceptedByKeyPressedMenuInput(): void
    {
        $block = Sensing::keyPressed(Key::UpArrow);
        $blocks = $block->toBlocks();

        $slot = $blocks[$block->id]['inputs']['KEY_OPTION'];
        self::assertSame(1, $slot[0]); // shadow reference
        $menuId = $slot[1];
        self::assertSame('sensing_keyoptions', $blocks[$menuId]['opcode']);
        self::assertSame(['up arrow', null], $blocks[$menuId]['fields']['KEY_OPTION']);
    }

    public function testKeyPressedStillAcceptsLooseString(): void
    {
        // Loose string remains supported for extensions / unknown keys
        $block = Sensing::keyPressed('custom-key');
        $blocks = $block->toBlocks();
        $menuId = $blocks[$block->id]['inputs']['KEY_OPTION'][1];
        self::assertSame(['custom-key', null], $blocks[$menuId]['fields']['KEY_OPTION']);
    }

    public function testMenuFactoryProducesShadowMenuBlock(): void
    {
        $block = Motion::goTo('Sprite1');
        $blocks = $block->toBlocks();

        $slot = $blocks[$block->id]['inputs']['TO'];
        self::assertSame(1, $slot[0]); // shadow reference
        $menuId = $slot[1];
        self::assertSame('motion_goto_menu', $blocks[$menuId]['opcode']);
        self::assertTrue($blocks[$menuId]['shadow']);
        self::assertSame(['Sprite1', null], $blocks[$menuId]['fields']['TO']);
    }

    public function testWhenSpriteClickedUsesOpcodeScratchRecognises(): void
    {
        // Scratch 3 silently drops scripts whose hat opcode it does not know.
        self::assertSame('event_whenthisspriteclicked', $this->entry(Events::whenSpriteClicked())['opcode']);

        $project = ScratchProject::create();
        $project->addSprite('Cat')->addScript(Events::whenSpriteClicked()->then(Looks::say('hi')));
        $result = ScratchValidator::validate($project);
        self::assertSame([], array_filter(
            $result->errors,
            static fn(ValidationError $e): bool => $e->code === ErrorCode::UNKNOWN_OPCODE,
        ));
        self::assertTrue($result->isValid);
    }

    public function testNestedReportersValidate(): void
    {
        $project = ScratchProject::create();
        $project->addSprite('Cat')->addScript(
            Events::whenFlagClicked()->then(
                Control::forever([
                    Control::if(
                        Operators::gt(Sensing::timer(), 5),
                        [
                            Looks::say(Operators::join('time: ', Sensing::timer())),
                            Control::stop(StopOption::All),
                        ],
                    ),
                ]),
            ),
        );
        self::assertTrue(ScratchValidator::validate($project)->isValid);
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(Block $block): array
    {
        return $block->toBlocks()[$block->id];
    }
}
