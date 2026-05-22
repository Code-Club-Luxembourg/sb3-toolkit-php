<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Cli\Command;

use CodeClubLuxembourg\Sb3Toolkit\Scratch\Paths;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'sprite-extract', description: 'Extract a single sprite from an .sb3 into a .sprite3 file')]
final class SpriteExtractCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
                Reads <project>, finds the sprite named --sprite, and writes a .sprite3
                archive containing the sprite's JSON plus the costumes/sounds it
                references. The output file can be dropped into Scratch's editor or
                imported into another project with `sprite-import`.

                Example:
                  bin/sb3 sprite-extract project.sb3 -s Cat -o cat.sprite3
                HELP)
            ->addArgument('project', InputArgument::REQUIRED, 'Source .sb3 file')
            ->addOption('sprite', 's', InputOption::VALUE_REQUIRED, 'Name of the sprite to extract')
            ->addOption('out', 'o', InputOption::VALUE_REQUIRED, 'Output .sprite3 file path');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $project */
        $project = $input->getArgument('project');
        $sprite = $input->getOption('sprite');
        $out = $input->getOption('out');
        if (!\is_string($sprite) || $sprite === '') {
            throw new InvalidOptionException('The --sprite option is required.');
        }
        if (!\is_string($out) || $out === '') {
            throw new InvalidOptionException('The --out option is required.');
        }

        $resolved = Paths::resolve([$project]);
        $inputPath = $resolved[0];
        $outputPath = Paths::toAbsolute($out);

        $loaded = ScratchProject::fromSb3File($inputPath);
        $loaded->extractSprite($sprite, $outputPath);

        $output->writeln(\sprintf('Extracted sprite "%s" from %s -> %s', $sprite, basename($inputPath), basename($outputPath)));

        return Command::SUCCESS;
    }
}
