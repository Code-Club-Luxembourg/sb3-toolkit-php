<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Validator\Rule;

use CodeClubLuxembourg\Sb3Toolkit\Validator\ErrorCode;
use CodeClubLuxembourg\Sb3Toolkit\Validator\OpcodeRegistry;
use CodeClubLuxembourg\Sb3Toolkit\Validator\OpcodeSpec;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationError;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationPath;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationSeverity;

/**
 * Validation of the block graph within a target.
 */
final class BlocksRule
{
    /**
     * @param array<string, mixed>  $target
     * @param list<ValidationError> $errors
     */
    public static function validate(
        array $target,
        int $targetIndex,
        string $targetPath,
        OpcodeRegistry $registry,
        bool $strictOpcodes,
        array &$errors,
    ): void {
        $blocks = $target['blocks'] ?? [];
        if (!\is_array($blocks)) {
            return;
        }
        $blockPath = "{$targetPath}.blocks";

        foreach ($blocks as $id => $block) {
            if ($block === null || !\is_array($block)) {
                continue;
            }
            self::validateBlock((string) $id, $block, $blocks, $targetIndex, $blockPath, $registry, $strictOpcodes, $errors);
        }

        self::detectCycles($blocks, $targetIndex, $blockPath, $errors);
    }

    /**
     * @param array<string, mixed>  $block
     * @param array<string, mixed>  $blocks
     * @param list<ValidationError> $errors
     */
    private static function validateBlock(
        string $id,
        array $block,
        array $blocks,
        int $targetIndex,
        string $blockPath,
        OpcodeRegistry $registry,
        bool $strictOpcodes,
        array &$errors,
    ): void {
        $path = "{$blockPath}.{$id}";

        $opcode = $block['opcode'] ?? null;
        if (!\is_string($opcode) || $opcode === '') {
            $errors[] = new ValidationError(
                ErrorCode::MISSING_FIELD,
                new ValidationPath("{$path}.opcode", $targetIndex, $id),
                'Block opcode must be a non-empty string',
            );

            return;
        }

        self::validateLinks($id, $block, $blocks, $targetIndex, $path, $errors);

        if (!empty($block['topLevel']) && (!\array_key_exists('x', $block) || !\array_key_exists('y', $block))) {
            $errors[] = new ValidationError(
                ErrorCode::TOPLEVEL_NO_COORDS,
                new ValidationPath($path, $targetIndex, $id),
                \sprintf('Top-level block "%s" is missing x/y coordinates', $id),
                ValidationSeverity::Warning,
            );
        }

        if (!empty($block['shadow']) && ($block['parent'] ?? null) === null) {
            $errors[] = new ValidationError(
                ErrorCode::SHADOW_NO_PARENT,
                new ValidationPath($path, $targetIndex, $id),
                \sprintf('Shadow block "%s" has no parent', $id),
                ValidationSeverity::Warning,
            );
        }

        $spec = $registry->get($opcode);
        if (!$spec instanceof OpcodeSpec) {
            $errors[] = new ValidationError(
                ErrorCode::UNKNOWN_OPCODE,
                new ValidationPath("{$path}.opcode", $targetIndex, $id),
                \sprintf('Unknown opcode "%s"', $opcode),
                $strictOpcodes ? ValidationSeverity::Error : ValidationSeverity::Warning,
            );

            return;
        }

        $inputs = \is_array($block['inputs'] ?? null) ? $block['inputs'] : [];
        $fields = \is_array($block['fields'] ?? null) ? $block['fields'] : [];

        // Skip required-input checks for procedures_call (inputs come from mutation).
        if ($opcode !== 'procedures_call') {
            foreach ($spec->requiredInputs as $inputName) {
                if (!\array_key_exists($inputName, $inputs)) {
                    $errors[] = new ValidationError(
                        ErrorCode::MISSING_INPUT,
                        new ValidationPath("{$path}.inputs.{$inputName}", $targetIndex, $id),
                        \sprintf('Block "%s" (%s) is missing required input "%s"', $id, $opcode, $inputName),
                    );
                }
            }
        }

        foreach ($spec->requiredFields as $fieldName) {
            if (!\array_key_exists($fieldName, $fields)) {
                $errors[] = new ValidationError(
                    ErrorCode::MISSING_FIELD_VALUE,
                    new ValidationPath("{$path}.fields.{$fieldName}", $targetIndex, $id),
                    \sprintf('Block "%s" (%s) is missing required field "%s"', $id, $opcode, $fieldName),
                );
            }
        }

        self::validateInputRefs($id, $inputs, $blocks, $targetIndex, $path, $errors);
    }

    /**
     * @param array<string, mixed>  $block
     * @param array<string, mixed>  $blocks
     * @param list<ValidationError> $errors
     */
    private static function validateLinks(
        string $id,
        array $block,
        array $blocks,
        int $targetIndex,
        string $path,
        array &$errors,
    ): void {
        $next = $block['next'] ?? null;
        if ($next !== null) {
            if (!\array_key_exists($next, $blocks)) {
                $errors[] = new ValidationError(
                    ErrorCode::DANGLING_NEXT,
                    new ValidationPath("{$path}.next", $targetIndex, $id),
                    \sprintf('Block "%s" has next "%s" which does not exist', $id, $next),
                );
            } else {
                $nextBlock = $blocks[$next];
                if (\is_array($nextBlock) && ($nextBlock['parent'] ?? null) !== $id) {
                    $errors[] = new ValidationError(
                        ErrorCode::INCONSISTENT_NEXT_PARENT,
                        new ValidationPath("{$path}.next", $targetIndex, $id),
                        \sprintf(
                            'Block "%s" has next "%s" but that block\'s parent is "%s" (expected "%s")',
                            $id,
                            $next,
                            self::stringifyParent($nextBlock['parent'] ?? null),
                            $id,
                        ),
                    );
                }
            }
        }

        $parent = $block['parent'] ?? null;
        if ($parent !== null && !\array_key_exists($parent, $blocks)) {
            $errors[] = new ValidationError(
                ErrorCode::DANGLING_PARENT,
                new ValidationPath("{$path}.parent", $targetIndex, $id),
                \sprintf('Block "%s" has parent "%s" which does not exist', $id, $parent),
            );
        }
    }

    /**
     * @param array<string, mixed>  $inputs
     * @param array<string, mixed>  $blocks
     * @param list<ValidationError> $errors
     */
    private static function validateInputRefs(
        string $id,
        array $inputs,
        array $blocks,
        int $targetIndex,
        string $path,
        array &$errors,
    ): void {
        foreach ($inputs as $inputName => $input) {
            if (!\is_array($input)) {
                continue;
            }
            $kind = $input[0] ?? null;
            $value = $input[1] ?? null;
            // kind 2 = no-shadow (block only), kind 3 = block over shadow
            if (($kind === 2 || $kind === 3) && \is_string($value) && !\array_key_exists($value, $blocks)) {
                $errors[] = new ValidationError(
                    ErrorCode::DANGLING_INPUT_REF,
                    new ValidationPath("{$path}.inputs.{$inputName}", $targetIndex, $id),
                    \sprintf('Block "%s" input "%s" references block "%s" which does not exist', $id, $inputName, $value),
                );
            }
            $shadowId = $input[2] ?? null;
            if ($kind === 3 && \is_string($shadowId) && !\array_key_exists($shadowId, $blocks)) {
                $errors[] = new ValidationError(
                    ErrorCode::DANGLING_INPUT_REF,
                    new ValidationPath("{$path}.inputs.{$inputName}", $targetIndex, $id),
                    \sprintf('Block "%s" input "%s" shadow references block "%s" which does not exist', $id, $inputName, $shadowId),
                );
            }
        }
    }

    /**
     * @param array<string, mixed>  $blocks
     * @param list<ValidationError> $errors
     */
    private static function detectCycles(array $blocks, int $targetIndex, string $blockPath, array &$errors): void
    {
        $visited = [];
        $cyclesReported = [];

        foreach ($blocks as $id => $block) {
            if ($block === null || isset($visited[(string) $id])) {
                continue;
            }

            $chain = [];
            $chainIndex = [];
            $current = (string) $id;

            while (true) {
                if (isset($chainIndex[$current])) {
                    $cycle = \array_slice($chain, $chainIndex[$current]);
                    self::reportCycle($cycle, $current, $targetIndex, $blockPath, $cyclesReported, $errors);
                    break;
                }
                if (isset($visited[$current])) {
                    break;
                }
                $chainIndex[$current] = \count($chain);
                $chain[] = $current;
                $visited[$current] = true;

                $currentBlock = $blocks[$current] ?? null;
                $next = \is_array($currentBlock) ? ($currentBlock['next'] ?? null) : null;
                if (!\is_string($next) || $next === '') {
                    break;
                }
                $current = $next;
            }
        }
    }

    /**
     * @param list<string>          $cycle  the ordered chain of block ids in the cycle
     * @param array<string, true>   $cyclesReported  signature → true, mutated to dedupe
     * @param list<ValidationError> $errors
     */
    private static function reportCycle(
        array $cycle,
        string $closingId,
        int $targetIndex,
        string $blockPath,
        array &$cyclesReported,
        array &$errors,
    ): void {
        $minId = min($cycle);
        $signature = implode('|', $cycle) . '|' . $minId;
        if (isset($cyclesReported[$signature])) {
            return;
        }
        $cyclesReported[$signature] = true;

        $path = implode(' → ', [...$cycle, $closingId]);
        $errors[] = new ValidationError(
            ErrorCode::BLOCK_CYCLE,
            new ValidationPath($blockPath, $targetIndex),
            \sprintf('Cycle detected in block next-chain: %s', $path),
        );
    }

    private static function stringifyParent(mixed $parent): string
    {
        return $parent === null ? 'null' : (string) $parent;
    }
}
