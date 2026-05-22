<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Cli;

use CodeClubLuxembourg\Sb3Toolkit\Cli\Command\GridCommand;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Command\InfoCommand;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Command\MergeCommand;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Command\PackCommand;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Command\ProjectDownloadCommand;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Command\SpriteExtractCommand;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Command\SpriteImportCommand;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Command\StudioDownloadCommand;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Command\TrimCommand;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Command\ValidateCommand;
use Symfony\Component\Console\Application;

/**
 * Builds the configured CLI application.
 */
final class CliFactory
{
    public static function create(): Application
    {
        $application = new Application('sb3-toolkit', '1.0.0');
        $application->addCommands([
            new MergeCommand(),
            new GridCommand(),
            new PackCommand(),
            new ProjectDownloadCommand(),
            new StudioDownloadCommand(),
            new ValidateCommand(),
            new InfoCommand(),
            new TrimCommand(),
            new SpriteExtractCommand(),
            new SpriteImportCommand(),
        ]);

        return $application;
    }
}
