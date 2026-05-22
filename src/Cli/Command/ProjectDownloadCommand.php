<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Cli\Command;

use CodeClubLuxembourg\Sb3Toolkit\Studio\StudioDownloader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'project-download', description: 'Download a specific Scratch project via the Scratch API')]
final class ProjectDownloadCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('project_id', InputArgument::REQUIRED, 'Scratch project ID')
            ->addOption('output-dir', 'o', InputOption::VALUE_REQUIRED, 'Directory to store downloaded .sb3 files', 'project_downloads')
            ->addOption('delay', null, InputOption::VALUE_REQUIRED, 'Delay in seconds between API requests', '0.2')
            ->addOption('no-skip-existing', null, InputOption::VALUE_NONE, 'Download even if the target .sb3 exists and metadata indicates no changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputDir = (string) $input->getOption('output-dir');
        if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true) && !is_dir($outputDir)) {
            throw new ConsoleRuntimeException(\sprintf('Failed to create output directory: %s', $outputDir));
        }

        $projectId = (int) $input->getArgument('project_id');
        if ($projectId <= 0) {
            throw new InvalidArgumentException(\sprintf('Invalid project ID: %s', $input->getArgument('project_id')));
        }

        $delay = (float) $input->getOption('delay');
        if ($delay < 0.0) {
            throw new InvalidOptionException('The --delay option must be zero or positive.');
        }
        $skipExisting = !$input->getOption('no-skip-existing');

        $downloader = new StudioDownloader(log: new ConsoleLogger($output));
        $outcome = $downloader->downloadAndSaveProject($outputDir, $projectId, $delay, $skipExisting);

        $output->writeln(\sprintf('Done. Downloaded %d project(s).', $outcome === 'downloaded' ? 1 : 0));

        return Command::SUCCESS;
    }
}
