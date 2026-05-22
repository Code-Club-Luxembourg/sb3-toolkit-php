<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Cli;

use InvalidArgumentException;

/**
 * Parsing helpers for numeric CLI options.
 */
final class NumberParser
{
    /**
     * Parse a positive integer or throw with a labeled error message.
     *
     * Mirrors JavaScript's `parseInt`: a leading integer is accepted (e.g.
     * "12px" yields 12); values without a leading integer or that are not
     * positive are rejected.
     */
    public static function positiveInt(string $value, string $label): int
    {
        if (preg_match('/^\s*[+-]?\d+/', $value) !== 1) {
            throw new InvalidArgumentException(\sprintf('%s must be a positive integer.', $label));
        }

        $parsed = (int) $value;
        if ($parsed <= 0) {
            throw new InvalidArgumentException(\sprintf('%s must be a positive integer.', $label));
        }

        return $parsed;
    }
}
