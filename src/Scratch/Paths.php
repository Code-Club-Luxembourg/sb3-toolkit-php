<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Scratch;

use RuntimeException;

/**
 * Path resolution helpers for the CLI.
 */
final class Paths
{
    /**
     * Resolve input paths to absolute paths and verify each file exists.
     *
     * @param list<string> $paths
     *
     * @return list<string>
     */
    public static function resolve(array $paths): array
    {
        $resolved = [];
        foreach ($paths as $input) {
            $full = self::toAbsolute($input);
            if (!file_exists($full)) {
                throw new RuntimeException(\sprintf('File not found: %s', $input));
            }
            $resolved[] = $full;
        }

        return $resolved;
    }

    /**
     * Resolve a single path to an absolute path (without requiring existence).
     */
    public static function toAbsolute(string $path): string
    {
        if (self::isAbsolute($path)) {
            return $path;
        }

        return getcwd() . \DIRECTORY_SEPARATOR . $path;
    }

    private static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }
}
