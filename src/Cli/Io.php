<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Cli;

use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;

/**
 * Shared CLI input/output helpers.
 */
final class Io
{
    /**
     * Load multiple `.sb3` files into {@see ScratchProject} instances.
     *
     * @param list<string> $paths
     *
     * @return list<ScratchProject>
     */
    public static function loadProjects(array $paths): array
    {
        $projects = [];
        foreach ($paths as $path) {
            $projects[] = ScratchProject::fromSb3File($path);
        }

        return $projects;
    }
}
