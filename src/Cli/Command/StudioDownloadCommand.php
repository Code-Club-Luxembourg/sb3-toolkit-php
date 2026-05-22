<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Cli\Command;

use CodeClubLuxembourg\Sb3Toolkit\Studio\StudioDownloader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'studio-download', description: 'Download all projects from a Scratch studio via the Scratch API')]
final class StudioDownloadCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('studio_id', InputArgument::REQUIRED, 'Scratch studio ID')
            ->addOption('output-dir', 'o', InputOption::VALUE_REQUIRED, 'Directory to store downloaded .sb3 files', 'studio_downloads')
            ->addOption('page-size', null, InputOption::VALUE_REQUIRED, 'Projects per API page', '40')
            ->addOption('max-projects', null, InputOption::VALUE_REQUIRED, 'Stop after this many projects (0 means no limit)', '0')
            ->addOption('delay', null, InputOption::VALUE_REQUIRED, 'Delay in seconds between API requests', '0.2')
            ->addOption('no-skip-existing', null, InputOption::VALUE_NONE, 'Download even if the target .sb3 exists and metadata indicates no changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputDir = (string) $input->getOption('output-dir');
        if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true) && !is_dir($outputDir)) {
            throw new ConsoleRuntimeException(\sprintf('Failed to create output directory: %s', $outputDir));
        }

        $studioId = (string) $input->getArgument('studio_id');
        $pageSize = (int) $input->getOption('page-size');
        if ($pageSize <= 0) {
            throw new InvalidOptionException('The --page-size option must be a positive integer.');
        }
        $maxProjects = (int) $input->getOption('max-projects');
        if ($maxProjects < 0) {
            throw new InvalidOptionException('The --max-projects option must be zero or positive.');
        }
        $delay = (float) $input->getOption('delay');
        if ($delay < 0.0) {
            throw new InvalidOptionException('The --delay option must be zero or positive.');
        }
        $skipExisting = !$input->getOption('no-skip-existing');

        $downloader = new StudioDownloader(log: new ConsoleLogger($output));

        $downloaded = 0;
        $failed = 0;
        foreach ($downloader->iterStudioProjects($studioId, $pageSize, $delay) as $item) {
            if ($maxProjects > 0 && $downloaded >= $maxProjects) {
                break;
            }

            $projectId = (int) ($item['id'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }

            try {
                if ($downloader->downloadAndSaveProject($outputDir, $projectId, $delay, $skipExisting) === 'downloaded') {
                    ++$downloaded;
                }
            } catch (Throwable $error) {
                $output->writeln(\sprintf('Failed %d: %s', $projectId, $error->getMessage()));
                ++$failed;
            }
        }

        $summary = $failed > 0 ? \sprintf(' (%d failed)', $failed) : '';
        $output->writeln(\sprintf('Done. Downloaded %d project(s).%s', $downloaded, $summary));

        return Command::SUCCESS;
    }
}
