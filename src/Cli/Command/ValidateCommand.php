<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Cli\Command;

use CodeClubLuxembourg\Sb3Toolkit\Scratch\Paths;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use CodeClubLuxembourg\Sb3Toolkit\Validator\Rule\OrphanedBlocksRule;
use CodeClubLuxembourg\Sb3Toolkit\Validator\Rule\UnusedVariablesRule;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ScratchValidator;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationSeverity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'validate', description: 'Validate one or more .sb3 projects against the structural + semantic rules')]
final class ValidateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
                Validates each .sb3 against the built-in rules (structure, block graph,
                opcode registry, asset references, variable references). Exits with
                code 1 when any project has error-severity findings.

                --strict-opcodes promotes unknown opcodes from warning to error.
                --semantic adds OrphanedBlocksRule + UnusedVariablesRule.
                --warnings-as-errors fails the run when *any* finding exists.

                Example:
                  bin/sb3 validate project.sb3
                  bin/sb3 validate *.sb3 --semantic --strict-opcodes
                HELP)
            ->addArgument('files', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'One or more .sb3 files')
            ->addOption('strict-opcodes', null, InputOption::VALUE_NONE, 'Treat unknown opcodes as errors')
            ->addOption('semantic', null, InputOption::VALUE_NONE, 'Enable orphaned-block + unused-variable rules')
            ->addOption('warnings-as-errors', null, InputOption::VALUE_NONE, 'Fail the run when any warning is present');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $files */
        $files = $input->getArgument('files');
        $strict = (bool) $input->getOption('strict-opcodes');
        $semantic = (bool) $input->getOption('semantic');
        $warningsAsErrors = (bool) $input->getOption('warnings-as-errors');

        $resolved = Paths::resolve($files);

        $totalErrors = 0;
        $totalWarnings = 0;
        $exitCode = Command::SUCCESS;
        foreach ($resolved as $path) {
            $project = ScratchProject::fromSb3File($path);
            $validator = new ScratchValidator(assets: $project->assets, strictOpcodes: $strict);
            if ($semantic) {
                $validator = $validator
                    ->withRule(new OrphanedBlocksRule())
                    ->withRule(new UnusedVariablesRule());
            }
            $result = $validator->run($project);

            $errors = 0;
            $warnings = 0;
            foreach ($result->errors as $error) {
                if ($error->severity === ValidationSeverity::Error) {
                    ++$errors;
                } else {
                    ++$warnings;
                }
                $output->writeln(\sprintf(
                    '[%s] %s — %s %s: %s',
                    $error->severity->value,
                    basename($path),
                    $error->code->value,
                    (string) $error->path,
                    $error->message,
                ));
            }

            $output->writeln(\sprintf(
                '%s: %d error(s), %d warning(s)',
                basename($path),
                $errors,
                $warnings,
            ));

            $totalErrors += $errors;
            $totalWarnings += $warnings;
            if (!$result->isValid || ($warningsAsErrors && $warnings > 0)) {
                $exitCode = Command::FAILURE;
            }
        }

        $output->writeln(\sprintf(
            "\nTotal: %d project(s), %d error(s), %d warning(s)",
            \count($resolved),
            $totalErrors,
            $totalWarnings,
        ));

        return $exitCode;
    }
}
