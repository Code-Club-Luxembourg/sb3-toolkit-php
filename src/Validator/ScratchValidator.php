<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Validator;

use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchAssetIndex;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use CodeClubLuxembourg\Sb3Toolkit\Validator\Rule\AssetsRule;
use CodeClubLuxembourg\Sb3Toolkit\Validator\Rule\BlocksRule;
use CodeClubLuxembourg\Sb3Toolkit\Validator\Rule\Rule;
use CodeClubLuxembourg\Sb3Toolkit\Validator\Rule\StructureRule;
use CodeClubLuxembourg\Sb3Toolkit\Validator\Rule\VariablesRule;

/**
 * Validates a {@see ScratchProject} and returns structured findings.
 */
final class ScratchValidator
{
    private readonly OpcodeRegistry $registry;

    /**
     * Extra user-supplied rules appended after the built-ins. Not readonly so
     * {@see withRule()} can populate the cloned validator.
     *
     * @var list<Rule>
     */
    private array $customRules = [];

    /**
     * @param ScratchAssetIndex|null $assets        when provided, enables {@see Rule\AssetsRule}
     *                                              to verify every costume/sound `md5ext` is present
     * @param bool                   $strictOpcodes when true, unknown opcodes are reported as
     *                                              {@see ValidationSeverity::Error} instead of `Warning`
     * @param OpcodeRegistry|null    $registry      override the built-in opcode registry (e.g.
     *                                              to add extension opcodes via {@see OpcodeRegistry::withSpecs()})
     */
    public function __construct(
        private readonly ?ScratchAssetIndex $assets = null,
        private readonly bool $strictOpcodes = false,
        ?OpcodeRegistry $registry = null,
    ) {
        $this->registry = $registry ?? OpcodeRegistry::default();
    }

    /**
     * Return a new validator with `$rule` appended to the rule chain. The
     * built-in rules always run first; user rules run after and see the same
     * project state, so they can layer additional checks on top.
     */
    public function withRule(Rule $rule): self
    {
        $clone = clone $this;
        $clone->customRules[] = $rule;

        return $clone;
    }

    /**
     * Run every rule against `$project` and return the aggregated findings.
     */
    public function run(ScratchProject $project): ValidationResult
    {
        $errors = [];
        $data = $project->data;

        StructureRule::validate($data, $errors);

        $targets = $data['targets'] ?? null;
        if (!\is_array($targets)) {
            return ValidationResult::fromErrors($errors);
        }

        $stage = null;
        foreach ($targets as $target) {
            if (\is_array($target) && ($target['isStage'] ?? null) === true) {
                $stage = $target;
                break;
            }
        }

        foreach ($targets as $index => $target) {
            if (!\is_array($target)) {
                continue;
            }
            $targetPath = \sprintf('targets[%d]', $index);

            BlocksRule::validate($target, $index, $targetPath, $this->registry, $this->strictOpcodes, $errors);

            if ($this->assets instanceof ScratchAssetIndex) {
                AssetsRule::validate($target, $index, $targetPath, $this->assets, $errors);
            }

            if ($stage !== null) {
                VariablesRule::validate($target, $index, $targetPath, $stage, $errors);
            }
        }

        foreach ($this->customRules as $rule) {
            foreach ($rule->validate($project) as $error) {
                $errors[] = $error;
            }
        }

        return ValidationResult::fromErrors($errors);
    }

    /**
     * Validate a project in a single call.
     *
     * @param list<Rule> $rules extra user-supplied rules, appended after the built-ins
     */
    public static function validate(
        ScratchProject $project,
        ?ScratchAssetIndex $assets = null,
        bool $strictOpcodes = false,
        ?OpcodeRegistry $registry = null,
        array $rules = [],
    ): ValidationResult {
        $validator = new self($assets, $strictOpcodes, $registry);
        foreach ($rules as $rule) {
            $validator = $validator->withRule($rule);
        }

        return $validator->run($project);
    }
}
