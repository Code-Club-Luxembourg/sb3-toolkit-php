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

#[AsCommand(name: 'sprite-import', description: 'Inject a .sprite3 into an .sb3 project as a new sprite')]
final class SpriteImportCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
                Reads <project> and one or more .sprite3 archives, adds each as a new
                sprite, and writes the result to --out (or back to <project> with
                --in-place). Name collisions get " (n)" appended.

                Example:
                  bin/sb3 sprite-import project.sb3 cat.sprite3 -o new.sb3
                HELP)
            ->addArgument('project', InputArgument::REQUIRED, 'Target .sb3 file')
            ->addArgument('sprites', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'One or more .sprite3 files')
            ->addOption('out', 'o', InputOption::VALUE_REQUIRED, 'Output .sb3 file path')
            ->addOption('in-place', null, InputOption::VALUE_NONE, 'Overwrite the input project');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $project */
        $project = $input->getArgument('project');
        /** @var list<string> $sprites */
        $sprites = $input->getArgument('sprites');
        $inPlace = (bool) $input->getOption('in-place');
        $out = $input->getOption('out');

        if ($inPlace && \is_string($out) && $out !== '') {
            throw new InvalidOptionException('--in-place and --out are mutually exclusive.');
        }
        if (!$inPlace && (!\is_string($out) || $out === '')) {
            throw new InvalidOptionException('Pass --out=<path> or --in-place.');
        }

        $resolved = Paths::resolve([$project, ...$sprites]);
        $projectPath = $resolved[0];
        $spritePaths = \array_slice($resolved, 1);
        $outputPath = $inPlace ? $projectPath : Paths::toAbsolute((string) $out);

        $loaded = ScratchProject::fromSb3File($projectPath);
        $added = [];
        foreach ($spritePaths as $spritePath) {
            $sprite = $loaded->importSprite($spritePath);
            $added[] = $sprite->name();
        }
        $loaded->saveToSb3File($outputPath);

        $output->writeln(\sprintf(
            'Imported %d sprite(s) [%s] into %s -> %s',
            \count($added),
            implode(', ', $added),
            basename($projectPath),
            basename($outputPath),
        ));

        return Command::SUCCESS;
    }
}
