<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Cli\Command;

use CodeClubLuxembourg\Sb3Toolkit\Cli\Io;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\Paths;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'merge', description: 'Merge sprites from one or more .sb3 projects into a base project')]
final class MergeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
                Copies every sprite from each <sources> project into <base>, resolves name
                collisions by appending " (n)" to the duplicate, and writes the result to
                --out. Stage backdrops and stage-level variables/lists/blocks stay from
                <base>. Pass --sprites=a,b,c to merge only those sprite names.

                Example:
                  bin/sb3 merge cat.sb3 dog.sb3frog.sb3 -o zoo.sb3
                HELP)
            ->addArgument('base', InputArgument::REQUIRED, 'Base .sb3 project')
            ->addArgument('sources', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'One or more .sb3 projects to merge from')
            ->addOption('out', 'o', InputOption::VALUE_REQUIRED, 'Output .sb3 file path (required)')
            ->addOption('sprites', 's', InputOption::VALUE_REQUIRED, 'Comma-separated sprite names to merge (default: all)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $out = $input->getOption('out');
        if (!\is_string($out) || $out === '') {
            throw new InvalidOptionException('The --out option is required.');
        }

        /** @var string $base */
        $base = $input->getArgument('base');
        /** @var list<string> $sources */
        $sources = $input->getArgument('sources');

        $files = [$base, ...$sources];
        $resolved = Paths::resolve($files);
        $basePath = $resolved[0];
        $sourcePaths = \array_slice($resolved, 1);
        $outputPath = Paths::toAbsolute($out);

        $baseProject = ScratchProject::fromSb3File($basePath);
        $sourceProjects = Io::loadProjects($sourcePaths);

        $spriteNames = null;
        $spritesOption = $input->getOption('sprites');
        if (\is_string($spritesOption) && $spritesOption !== '') {
            $spriteNames = array_values(array_filter(array_map(trim(...), explode(',', $spritesOption)), static fn(string $n): bool => $n !== ''));
        }

        $merged = ScratchProject::mergeSprites($baseProject, $sourceProjects, $spriteNames);
        $merged->saveToSb3File($outputPath);

        $output->writeln(\sprintf(
            'Merged %d project(s) from %s into %s',
            \count($sourceProjects),
            basename($basePath),
            basename($outputPath),
        ));

        return Command::SUCCESS;
    }
}
