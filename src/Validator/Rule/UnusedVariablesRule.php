<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Validator\Rule;

use Generator;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ErrorCode;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationError;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationPath;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationSeverity;

/**
 * Flag variables and lists that are declared on a target (or the stage) but
 * never referenced by any block on the same target or any sprite. These are
 * not bugs — they just survived a refactor — but they bloat the project.
 *
 * Reported as warnings.
 */
final class UnusedVariablesRule implements Rule
{
    private const array VARIABLE_OPCODES = [
        'data_variable' => 'VARIABLE',
        'data_setvariableto' => 'VARIABLE',
        'data_changevariableby' => 'VARIABLE',
        'data_showvariable' => 'VARIABLE',
        'data_hidevariable' => 'VARIABLE',
    ];

    private const array LIST_OPCODES = [
        'data_listcontents' => 'LIST',
        'data_addtolist' => 'LIST',
        'data_deleteoflist' => 'LIST',
        'data_deletealloflist' => 'LIST',
        'data_insertatlist' => 'LIST',
        'data_replaceitemoflist' => 'LIST',
        'data_itemoflist' => 'LIST',
        'data_itemnumoflist' => 'LIST',
        'data_lengthoflist' => 'LIST',
        'data_listcontainsitem' => 'LIST',
        'data_showlist' => 'LIST',
        'data_hidelist' => 'LIST',
    ];

    public function validate(ScratchProject $project): Generator
    {
        $targets = $project->data['targets'] ?? null;
        if (!\is_array($targets)) {
            return;
        }

        [$varRefs, $listRefs] = self::collectReferences($targets);

        foreach ($targets as $targetIndex => $target) {
            if (!\is_array($target)) {
                continue;
            }
            foreach (($target['variables'] ?? []) as $id => $varEntry) {
                if (!\is_array($varEntry) || isset($varRefs[$id])) {
                    continue;
                }
                $name = \is_string($varEntry[0] ?? null) ? $varEntry[0] : (string) $id;
                yield new ValidationError(
                    ErrorCode::UNUSED_VARIABLE,
                    new ValidationPath(\sprintf('targets[%d].variables.%s', $targetIndex, $id), (int) $targetIndex),
                    \sprintf('Variable "%s" is declared but never referenced', $name),
                    ValidationSeverity::Warning,
                );
            }
            foreach (($target['lists'] ?? []) as $id => $listEntry) {
                if (!\is_array($listEntry) || isset($listRefs[$id])) {
                    continue;
                }
                $name = \is_string($listEntry[0] ?? null) ? $listEntry[0] : (string) $id;
                yield new ValidationError(
                    ErrorCode::UNUSED_LIST,
                    new ValidationPath(\sprintf('targets[%d].lists.%s', $targetIndex, $id), (int) $targetIndex),
                    \sprintf('List "%s" is declared but never referenced', $name),
                    ValidationSeverity::Warning,
                );
            }
        }
    }

    /**
     * @param array<int, mixed> $targets
     *
     * @return array{0: array<string, true>, 1: array<string, true>} [varIds, listIds]
     */
    private static function collectReferences(array $targets): array
    {
        $varRefs = [];
        $listRefs = [];
        foreach ($targets as $target) {
            if (!\is_array($target)) {
                continue;
            }
            foreach (($target['blocks'] ?? []) as $block) {
                if (!\is_array($block)) {
                    continue;
                }
                $opcode = $block['opcode'] ?? null;
                if (!\is_string($opcode)) {
                    continue;
                }
                $varField = self::VARIABLE_OPCODES[$opcode] ?? null;
                if ($varField !== null) {
                    $field = $block['fields'][$varField] ?? null;
                    if (\is_array($field) && \is_string($field[1] ?? null)) {
                        $varRefs[$field[1]] = true;
                    }
                }
                $listField = self::LIST_OPCODES[$opcode] ?? null;
                if ($listField !== null) {
                    $field = $block['fields'][$listField] ?? null;
                    if (\is_array($field) && \is_string($field[1] ?? null)) {
                        $listRefs[$field[1]] = true;
                    }
                }
            }
        }

        return [$varRefs, $listRefs];
    }
}
