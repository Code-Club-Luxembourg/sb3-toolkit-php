<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Cli\Command;

use CodeClubLuxembourg\Sb3Toolkit\Cli\Io;
use CodeClubLuxembourg\Sb3Toolkit\Cli\NumberParser;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\Paths;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'grid', description: 'Arrange multiple .sb3 projects into a grid in a new project')]
final class GridCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
                Tiles every <sources> project side-by-side in a --columns × --rows grid
                inside a single .sb3 stage. Stage backdrops are stacked, sprite positions
                and absolute-coordinate motion blocks are offset by the cell's stage size
                so each project lands in its own cell. --rows defaults to
                ceil(count / columns).

                Example:
                  bin/sb3 grid a.sb3 b.sb3 c.sb3 d.sb3 --columns 2 -o grid.sb3
                HELP)
            ->addArgument('sources', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'One or more .sb3 projects to place in the grid')
            ->addOption('out', 'o', InputOption::VALUE_REQUIRED, 'Output .sb3 file path (required)')
            ->addOption('columns', 'c', InputOption::VALUE_REQUIRED, 'Number of columns in the grid (required)')
            ->addOption('rows', 'r', InputOption::VALUE_REQUIRED, 'Number of rows in the grid (default: ceil(count / columns))');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $out = $input->getOption('out');
        if (!\is_string($out) || $out === '') {
            throw new InvalidOptionException('The --out option is required.');
        }
        $columnsOption = $input->getOption('columns');
        if (!\is_string($columnsOption) || $columnsOption === '') {
            throw new InvalidOptionException('The --columns option is required.');
        }

        $columns = NumberParser::positiveInt($columnsOption, 'Grid columns');
        $rowsOption = $input->getOption('rows');
        $rows = \is_string($rowsOption) && $rowsOption !== '' ? NumberParser::positiveInt($rowsOption, 'Grid rows') : null;

        /** @var list<string> $sources */
        $sources = $input->getArgument('sources');
        $sourcePaths = Paths::resolve($sources);
        $outputPath = Paths::toAbsolute($out);

        $projects = Io::loadProjects($sourcePaths);
        $effectiveRows = $rows ?? (int) ceil(\count($projects) / $columns);

        $grid = ScratchProject::gridProjects($projects, $columns, $rows);
        $grid->saveToSb3File($outputPath);

        $pixels = ScratchProject::gridPixelSize($columns, $effectiveRows);
        $output->writeln(\sprintf(
            'Gridded %d project(s) into %s (%dx%d, %dx%dpx)',
            \count($projects),
            basename($outputPath),
            $columns,
            $effectiveRows,
            $pixels['width'],
            $pixels['height'],
        ));

        return Command::SUCCESS;
    }
}
