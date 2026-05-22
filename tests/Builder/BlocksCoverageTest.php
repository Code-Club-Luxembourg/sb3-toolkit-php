<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Builder;

use PHPUnit\Framework\TestCase;
use CodeClubLuxembourg\Sb3Toolkit\Validator\OpcodeRegistry;

/**
 * Guards against drift between the enriched opcode metadata and the generated
 * category classes: every non-internal opcode must have a factory method, and
 * the generated files must be regenerated when the metadata changes.
 */
final class BlocksCoverageTest extends TestCase
{
    private const array CATEGORIES = [
        'motion' => 'Motion',
        'looks' => 'Looks',
        'sound' => 'Sound',
        'event' => 'Events',
        'control' => 'Control',
        'sensing' => 'Sensing',
        'operator' => 'Operators',
        'data' => 'Data',
        'pen' => 'Pen',
    ];

    public function testEveryNonInternalOpcodeHasAFactoryMethod(): void
    {
        foreach (OpcodeRegistry::specs() as $opcode => $spec) {
            if (!empty($spec['internal'])) {
                continue;
            }
            self::assertArrayHasKey('name', $spec, "Opcode {$opcode} is missing a factory method name");

            $prefix = substr($opcode, 0, (int) strpos($opcode, '_'));
            self::assertArrayHasKey($prefix, self::CATEGORIES, "No category mapping for {$opcode}");

            $class = 'CodeClubLuxembourg\\Sb3Toolkit\\Builder\\Blocks\\' . self::CATEGORIES[$prefix];
            self::assertTrue(
                method_exists($class, $spec['name']),
                "Expected {$class}::{$spec['name']}() for opcode {$opcode} — run `composer generate-blocks`",
            );
        }
    }
}
