<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder;

/**
 * Generates the random identifiers Scratch uses for blocks, variables and lists.
 */
final class Id
{
    private const string ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyz';

    /**
     * Generate a random ~20 character block/variable identifier.
     */
    public static function generate(): string
    {
        $id = '';
        for ($i = 0; $i < 20; ++$i) {
            $id .= self::ALPHABET[random_int(0, 35)];
        }

        return $id;
    }
}
