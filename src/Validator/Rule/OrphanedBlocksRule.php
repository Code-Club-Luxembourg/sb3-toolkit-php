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
 * Flag blocks that are present in the blocks map but unreachable from any
 * top-level (hat) block — i.e. they would never execute. These usually appear
 * after manual edits or partial deletions.
 *
 * Reported as warnings because they don't break parsing; the project still
 * loads and runs (sans the dead blocks). Use `withRule()` to enable.
 */
final class OrphanedBlocksRule implements Rule
{
    public function validate(ScratchProject $project): Generator
    {
        $targets = $project->data['targets'] ?? null;
        if (!\is_array($targets)) {
            return;
        }
        foreach ($targets as $targetIndex => $target) {
            if (!\is_array($target)) {
                continue;
            }
            $blocks = $target['blocks'] ?? [];
            if (!\is_array($blocks)) {
                continue;
            }
            $reachable = self::reachableFromHats($blocks);
            foreach ($blocks as $id => $block) {
                if (!\is_array($block) || isset($reachable[$id])) {
                    continue;
                }
                yield new ValidationError(
                    ErrorCode::ORPHANED_BLOCK,
                    new ValidationPath(\sprintf('targets[%d].blocks.%s', $targetIndex, $id), (int) $targetIndex, (string) $id),
                    \sprintf('Block "%s" (%s) is unreachable from any top-level script', $id, \is_string($block['opcode'] ?? null) ? $block['opcode'] : 'unknown'),
                    ValidationSeverity::Warning,
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $blocks
     *
     * @return array<string, true>
     */
    private static function reachableFromHats(array $blocks): array
    {
        $reachable = [];
        foreach ($blocks as $id => $block) {
            if (\is_array($block) && !empty($block['topLevel'])) {
                self::markReachable($blocks, (string) $id, $reachable);
            }
        }

        return $reachable;
    }

    /**
     * @param array<string, mixed> $blocks
     * @param array<string, true>  $reachable
     */
    private static function markReachable(array $blocks, string $id, array &$reachable): void
    {
        if (isset($reachable[$id]) || !isset($blocks[$id]) || !\is_array($blocks[$id])) {
            return;
        }
        $reachable[$id] = true;
        $block = $blocks[$id];

        foreach (($block['inputs'] ?? []) as $slot) {
            if (!\is_array($slot)) {
                continue;
            }
            if (isset($slot[1]) && \is_string($slot[1])) {
                self::markReachable($blocks, $slot[1], $reachable);
            }
            if (isset($slot[2]) && \is_string($slot[2])) {
                self::markReachable($blocks, $slot[2], $reachable);
            }
        }

        $next = $block['next'] ?? null;
        if (\is_string($next)) {
            self::markReachable($blocks, $next, $reachable);
        }
    }
}
