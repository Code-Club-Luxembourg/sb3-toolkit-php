<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Validator\Rule;

use CodeClubLuxembourg\Sb3Toolkit\Validator\ErrorCode;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationError;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationPath;

/**
 * Validation that variable/list references resolve to a definition.
 */
final class VariablesRule
{
    private const array VARIABLE_OPCODES = [
        'data_variable',
        'data_setvariableto',
        'data_changevariableby',
        'data_showvariable',
        'data_hidevariable',
    ];

    private const array LIST_OPCODES = [
        'data_listcontents',
        'data_addtolist',
        'data_deleteoflist',
        'data_deletealloflist',
        'data_insertatlist',
        'data_replaceitemoflist',
        'data_itemoflist',
        'data_itemnumoflist',
        'data_lengthoflist',
        'data_listcontainsitem',
        'data_showlist',
        'data_hidelist',
    ];

    /**
     * @param array<string, mixed>  $target
     * @param array<string, mixed>  $stage
     * @param list<ValidationError> $errors
     */
    public static function validate(array $target, int $targetIndex, string $targetPath, array $stage, array &$errors): void
    {
        $blocks = $target['blocks'] ?? [];
        if (!\is_array($blocks)) {
            return;
        }
        $blockPath = "{$targetPath}.blocks";

        $targetVariables = $target['variables'] ?? [];
        $stageVariables = $stage['variables'] ?? [];
        $targetLists = $target['lists'] ?? [];
        $stageLists = $stage['lists'] ?? [];

        foreach ($blocks as $id => $block) {
            if (!\is_array($block)) {
                continue;
            }
            $opcode = $block['opcode'] ?? null;
            $blockId = (string) $id;

            if (\in_array($opcode, self::VARIABLE_OPCODES, true)) {
                self::checkRef(
                    $block['fields']['VARIABLE'] ?? null,
                    [$targetVariables, $stageVariables],
                    ErrorCode::DANGLING_VARIABLE_REF,
                    'variable',
                    new ValidationPath(\sprintf('%s.%s.fields.VARIABLE', $blockPath, $blockId), $targetIndex, $blockId),
                    $blockId,
                    $errors,
                );
            }

            if (\in_array($opcode, self::LIST_OPCODES, true)) {
                self::checkRef(
                    $block['fields']['LIST'] ?? null,
                    [$targetLists, $stageLists],
                    ErrorCode::DANGLING_LIST_REF,
                    'list',
                    new ValidationPath(\sprintf('%s.%s.fields.LIST', $blockPath, $blockId), $targetIndex, $blockId),
                    $blockId,
                    $errors,
                );
            }
        }
    }

    /**
     * @param mixed                       $field   the `[name, id]` field tuple
     * @param array<int, array<mixed>>    $scopes  variable/list maps to search
     * @param list<ValidationError>       $errors
     */
    private static function checkRef(
        mixed $field,
        array $scopes,
        ErrorCode $code,
        string $kind,
        ValidationPath $path,
        string $blockId,
        array &$errors,
    ): void {
        if (!\is_array($field)) {
            return;
        }
        $refId = $field[1] ?? null;
        if (!\is_string($refId) || $refId === '') {
            return;
        }

        foreach ($scopes as $scope) {
            if (\is_array($scope) && \array_key_exists($refId, $scope)) {
                return;
            }
        }

        $name = $field[0] ?? '';
        $errors[] = new ValidationError(
            $code,
            $path,
            \sprintf(
                'Block "%s" references %s "%s" ("%s") which is not defined on this sprite or the stage',
                $blockId,
                $kind,
                $refId,
                \is_scalar($name) ? (string) $name : '',
            ),
        );
    }
}
