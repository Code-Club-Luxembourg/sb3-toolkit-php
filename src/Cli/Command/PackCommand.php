<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Cli\Command;

use CodeClubLuxembourg\Sb3Toolkit\Cli\NumberParser;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Packager\BridgeScript;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Packager\HtmlPackager;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Packager\PackagerInterface;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Packager\PackRequest;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\Paths;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pack', description: 'Package a .sb3 project into a standalone HTML file')]
final class PackCommand extends Command
{
    /**
     * @param PackagerInterface|null $packager overrides the default {@see HtmlPackager} (mainly for tests)
     */
    public function __construct(private readonly ?PackagerInterface $packager = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
                Bundles a .sb3 project into a single standalone HTML player driven by
                the vendored TurboWarp scaffolding runtime, no Node required at
                runtime. --stage-width/--stage-height default to Scratch's canonical
                480x360. --bridge wraps the player in postMessage forwarding for
                embedding; --countdown-seconds adds an overlay that polls a checksum
                URL and reloads when it changes.

                Example:
                  bin/sb3 pack project.sb3 -o project.html --autoplay
                HELP)
            ->addArgument('project', InputArgument::REQUIRED, 'Input .sb3 project file')
            ->addOption('out', 'o', InputOption::VALUE_REQUIRED, 'Output HTML file path (required)')
            ->addOption('stage-width', null, InputOption::VALUE_REQUIRED, 'Stage width in pixels', '480')
            ->addOption('stage-height', null, InputOption::VALUE_REQUIRED, 'Stage height in pixels', '360')
            ->addOption('autoplay', null, InputOption::VALUE_NONE, 'Auto-start the project after load (green flag)')
            ->addOption('bridge', null, InputOption::VALUE_NONE, 'Forward Scratch broadcasts to the parent frame via postMessage')
            ->addOption('countdown-seconds', null, InputOption::VALUE_REQUIRED, 'Show a countdown overlay and reload if the project checksum changes')
            ->addOption('checksum-file', null, InputOption::VALUE_REQUIRED, 'Checksum file path (default: <output>.sha256)')
            ->addOption('checksum-url', null, InputOption::VALUE_REQUIRED, 'Checksum URL used by the HTML (default: basename of checksum file)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $out = $input->getOption('out');
        if (!\is_string($out) || $out === '') {
            throw new InvalidOptionException('The --out option is required.');
        }
        $stageWidth = NumberParser::positiveInt((string) $input->getOption('stage-width'), 'Stage width');
        $stageHeight = NumberParser::positiveInt((string) $input->getOption('stage-height'), 'Stage height');

        $countdownOption = $input->getOption('countdown-seconds');
        $countdownSeconds = \is_string($countdownOption) && $countdownOption !== ''
            ? NumberParser::positiveInt($countdownOption, 'Countdown seconds')
            : null;

        /** @var string $project */
        $project = $input->getArgument('project');
        [$projectPath] = Paths::resolve([$project]);
        $outputPath = Paths::toAbsolute($out);

        $checksumFile = $input->getOption('checksum-file');
        $checksumUrl = $input->getOption('checksum-url');

        $packager = $this->packager ?? new HtmlPackager();
        $packager->pack(new PackRequest(
            projectPath: $projectPath,
            outputPath: $outputPath,
            stageWidth: $stageWidth,
            stageHeight: $stageHeight,
            autoplay: (bool) $input->getOption('autoplay'),
            customJs: $input->getOption('bridge') ? BridgeScript::build() : null,
            countdownSeconds: $countdownSeconds,
            checksumFile: \is_string($checksumFile) ? $checksumFile : null,
            checksumUrl: \is_string($checksumUrl) ? $checksumUrl : null,
        ));

        $output->writeln(\sprintf('Packed %s to %s', basename($projectPath), basename($outputPath)));

        return Command::SUCCESS;
    }
}
