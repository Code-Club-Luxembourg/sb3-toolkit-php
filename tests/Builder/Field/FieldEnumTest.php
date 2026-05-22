<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Builder\Field;

use BackedEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\CurrentMenu;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\DragMode;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\ForwardBackward;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\FrontBack;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\GraphicEffect;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\GreaterThanMenu;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\Key;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\MathOp;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\NumberName;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\RotationStyle;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\SoundEffect;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\StopOption;

/**
 * The string `->value` of every Field enum case is the on-wire identifier
 * Scratch's runtime expects. Changing one is a silent breakage that would
 * produce projects the player won't accept, so the values are pinned by
 * fixture here. If a case is added, the fixture must be extended; if a value
 * needs to change, this test must be updated deliberately.
 */
final class FieldEnumTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, array<string, string>}>
     */
    public static function enumFixtures(): iterable
    {
        yield 'CurrentMenu' => [CurrentMenu::class, [
            'Year' => 'YEAR',
            'Month' => 'MONTH',
            'Date' => 'DATE',
            'DayOfWeek' => 'DAYOFWEEK',
            'Hour' => 'HOUR',
            'Minute' => 'MINUTE',
            'Second' => 'SECOND',
        ]];

        yield 'DragMode' => [DragMode::class, [
            'Draggable' => 'draggable',
            'NotDraggable' => 'not draggable',
        ]];

        yield 'ForwardBackward' => [ForwardBackward::class, [
            'Forward' => 'forward',
            'Backward' => 'backward',
        ]];

        yield 'FrontBack' => [FrontBack::class, [
            'Front' => 'front',
            'Back' => 'back',
        ]];

        yield 'GraphicEffect' => [GraphicEffect::class, [
            'Color' => 'COLOR',
            'Fisheye' => 'FISHEYE',
            'Whirl' => 'WHIRL',
            'Pixelate' => 'PIXELATE',
            'Mosaic' => 'MOSAIC',
            'Brightness' => 'BRIGHTNESS',
            'Ghost' => 'GHOST',
        ]];

        yield 'GreaterThanMenu' => [GreaterThanMenu::class, [
            'Loudness' => 'LOUDNESS',
            'Timer' => 'TIMER',
        ]];

        yield 'Key' => [Key::class, [
            'Any' => 'any',
            'Space' => 'space',
            'Enter' => 'enter',
            'LeftArrow' => 'left arrow',
            'RightArrow' => 'right arrow',
            'UpArrow' => 'up arrow',
            'DownArrow' => 'down arrow',
            'A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd', 'E' => 'e',
            'F' => 'f', 'G' => 'g', 'H' => 'h', 'I' => 'i', 'J' => 'j',
            'K' => 'k', 'L' => 'l', 'M' => 'm', 'N' => 'n', 'O' => 'o',
            'P' => 'p', 'Q' => 'q', 'R' => 'r', 'S' => 's', 'T' => 't',
            'U' => 'u', 'V' => 'v', 'W' => 'w', 'X' => 'x', 'Y' => 'y', 'Z' => 'z',
            'Digit0' => '0', 'Digit1' => '1', 'Digit2' => '2', 'Digit3' => '3',
            'Digit4' => '4', 'Digit5' => '5', 'Digit6' => '6', 'Digit7' => '7',
            'Digit8' => '8', 'Digit9' => '9',
        ]];

        yield 'MathOp' => [MathOp::class, [
            'Abs' => 'abs',
            'Floor' => 'floor',
            'Ceiling' => 'ceiling',
            'Sqrt' => 'sqrt',
            'Sin' => 'sin',
            'Cos' => 'cos',
            'Tan' => 'tan',
            'Asin' => 'asin',
            'Acos' => 'acos',
            'Atan' => 'atan',
            'Ln' => 'ln',
            'Log' => 'log',
            'EExp' => 'e ^',
            'TenExp' => '10 ^',
        ]];

        yield 'NumberName' => [NumberName::class, [
            'Number' => 'number',
            'Name' => 'name',
        ]];

        yield 'RotationStyle' => [RotationStyle::class, [
            'AllAround' => 'all around',
            'LeftRight' => 'left-right',
            'DontRotate' => "don't rotate",
        ]];

        yield 'SoundEffect' => [SoundEffect::class, [
            'Pitch' => 'PITCH',
            'Pan' => 'PAN',
        ]];

        yield 'StopOption' => [StopOption::class, [
            'All' => 'all',
            'ThisScript' => 'this script',
            'OtherScriptsInSprite' => 'other scripts in sprite',
        ]];
    }

    /**
     * @param class-string<BackedEnum> $enumClass
     * @param array<string, string>     $expected  case-name => Scratch wire value
     */
    #[DataProvider('enumFixtures')]
    public function testEnumCasesMatchFrozenFixture(string $enumClass, array $expected): void
    {
        $actual = [];
        foreach ($enumClass::cases() as $case) {
            $actual[$case->name] = $case->value;
        }

        self::assertSame(
            $expected,
            $actual,
            \sprintf(
                "%s drifted from its frozen Scratch wire values.\nIf this change is intentional, update the fixture in %s.",
                $enumClass,
                __FILE__,
            ),
        );
    }
}
