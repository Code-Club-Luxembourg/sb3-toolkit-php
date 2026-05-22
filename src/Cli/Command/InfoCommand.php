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

#[AsCommand(name: 'info', description: 'Print a summary of an .sb3 project: sprites, scripts, opcodes, variables, assets')]
final class InfoCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
                Loads each .sb3 and prints a one-line-per-fact summary: target count,
                script count, opcode histogram, variable / list / broadcast counts,
                asset count. Pass --top=N to limit the opcode histogram.

                Example:
                  bin/sb3 info project.sb3
                  bin/sb3 info project.sb3 --top=5
                HELP)
            ->addArgument('files', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'One or more .sb3 files')
            ->addOption('top', null, InputOption::VALUE_REQUIRED, 'Show the top N opcodes only (0 = all)', '10');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $files */
        $files = $input->getArgument('files');
        $top = max(0, (int) ($input->getOption('top') ?? 10));
        $resolved = Paths::resolve($files);

        foreach ($resolved as $path) {
            $project = ScratchProject::fromSb3File($path);
            $output->writeln(\sprintf('=== %s ===', basename($path)));

            $sprites = $project->listSprites();
            $stage = $project->getStage();
            $output->writeln(\sprintf('Targets: 1 stage + %d sprite(s)', \count($sprites)));

            $blockCount = 0;
            $scriptCount = 0;
            $opcodeCounts = [];
            $targets = $project->data['targets'] ?? [];
            foreach ($targets as $target) {
                if (!\is_array($target)) {
                    continue;
                }
                foreach (($target['blocks'] ?? []) as $block) {
                    if (!\is_array($block)) {
                        continue;
                    }
                    ++$blockCount;
                    if (!empty($block['topLevel'])) {
                        ++$scriptCount;
                    }
                    $opcode = \is_string($block['opcode'] ?? null) ? $block['opcode'] : '';
                    $opcodeCounts[$opcode] = ($opcodeCounts[$opcode] ?? 0) + 1;
                }
            }
            $output->writeln(\sprintf('Blocks: %d (%d top-level)', $blockCount, $scriptCount));

            $varCount = \count($stage['variables'] ?? []);
            foreach ($sprites as $sprite) {
                $varCount += \count($sprite['variables'] ?? []);
            }
            $listCount = \count($stage['lists'] ?? []);
            foreach ($sprites as $sprite) {
                $listCount += \count($sprite['lists'] ?? []);
            }
            $broadcastCount = \count($stage['broadcasts'] ?? []);
            $output->writeln(\sprintf('Variables: %d  Lists: %d  Broadcasts: %d', $varCount, $listCount, $broadcastCount));

            $output->writeln(\sprintf('Assets: %d file(s)', \count(iterator_to_array($project->assets->entries(), false))));

            if ($opcodeCounts !== []) {
                arsort($opcodeCounts);
                $shown = $top > 0 ? \array_slice($opcodeCounts, 0, $top, true) : $opcodeCounts;
                $output->writeln('Top opcodes:');
                foreach ($shown as $opcode => $count) {
                    $output->writeln(\sprintf('  %4d  %s', $count, $opcode === '' ? '(empty)' : $opcode));
                }
            }

            if (\count($sprites) > 0) {
                $output->writeln('Sprites:');
                foreach ($sprites as $sprite) {
                    $output->writeln(\sprintf(
                        '  - %s  (x=%s y=%s size=%s scripts=%d)',
                        $sprite['name'] ?? '?',
                        self::numberToString($sprite['x'] ?? 0),
                        self::numberToString($sprite['y'] ?? 0),
                        self::numberToString($sprite['size'] ?? 100),
                        self::countTopLevel($sprite['blocks'] ?? []),
                    ));
                }
            }

            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    private static function numberToString(int|float|string $n): string
    {
        return \is_string($n) ? $n : (string) $n;
    }

    /**
     * @param array<string, mixed> $blocks
     */
    private static function countTopLevel(array $blocks): int
    {
        $count = 0;
        foreach ($blocks as $block) {
            if (\is_array($block) && !empty($block['topLevel'])) {
                ++$count;
            }
        }

        return $count;
    }
}
