<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Cli;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use CodeClubLuxembourg\Sb3Toolkit\Cli\NumberParser;

final class NumberParserTest extends TestCase
{
    public function testParsesPositiveIntegers(): void
    {
        self::assertSame(2, NumberParser::positiveInt('2', 'Grid columns'));
        self::assertSame(480, NumberParser::positiveInt('480', 'Stage width'));
        self::assertSame(12, NumberParser::positiveInt('12px', 'Stage width'));
    }

    public function testRejectsNonNumericValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Grid columns must be a positive integer.');
        NumberParser::positiveInt('nope', 'Grid columns');
    }

    public function testRejectsZeroAndNegativeValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stage width must be a positive integer.');
        NumberParser::positiveInt('-3', 'Stage width');
    }
}
