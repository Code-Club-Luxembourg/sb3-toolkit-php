<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Cli\Command;

use CodeClubLuxembourg\Sb3Toolkit\Scratch\Paths;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'trim', description: 'Strip unreferenced assets from a .sb3 project and write a smaller file')]
final class TrimCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
                Reads <file>, drops every asset that isn't referenced by any costume or
                sound on any target, and writes the result to --out (or to <file> with
                --in-place). Useful when an editor accumulates orphan SVGs/MP3s after
                deletions.

                Example:
                  bin/sb3 trim project.sb3 -o trimmed.sb3
                  bin/sb3 trim project.sb3 --in-place
                HELP)
            ->addArgument('file', InputArgument::REQUIRED, 'Input .sb3 file')
            ->addOption('out', 'o', InputOption::VALUE_REQUIRED, 'Output .sb3 file path')
            ->addOption('in-place', null, InputOption::VALUE_NONE, 'Overwrite the input file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $file */
        $file = $input->getArgument('file');
        $inPlace = (bool) $input->getOption('in-place');
        $out = $input->getOption('out');

        if ($inPlace && \is_string($out) && $out !== '') {
            $output->writeln('<error>--in-place and --out are mutually exclusive.</error>');

            return Command::FAILURE;
        }
        if (!$inPlace && (!\is_string($out) || $out === '')) {
            $output->writeln('<error>Pass --out=<path> or --in-place.</error>');

            return Command::FAILURE;
        }

        $resolved = Paths::resolve([$file]);
        $inputPath = $resolved[0];
        $outputPath = $inPlace ? $inputPath : Paths::toAbsolute((string) $out);

        $project = ScratchProject::fromSb3File($inputPath);
        $before = \count($project->assets->entries());
        $removed = $project->trimAssets();
        $after = \count($project->assets->entries());
        $project->saveToSb3File($outputPath);

        $output->writeln(\sprintf(
            'Trimmed %s: %d → %d asset(s) (%d removed) -> %s',
            basename($inputPath),
            $before,
            $after,
            \count($removed),
            basename($outputPath),
        ));

        return Command::SUCCESS;
    }
}
