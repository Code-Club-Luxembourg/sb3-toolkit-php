<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Validator\Rule;

use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchAssetIndex;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ErrorCode;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationError;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationPath;

/**
 * Validation that referenced costume/sound assets are present.
 */
final class AssetsRule
{
    /**
     * @param array<string, mixed>  $target
     * @param list<ValidationError> $errors
     */
    public static function validate(
        array $target,
        int $targetIndex,
        string $targetPath,
        ScratchAssetIndex $assets,
        array &$errors,
    ): void {
        foreach (($target['costumes'] ?? []) as $index => $costume) {
            $md5ext = $costume['md5ext'] ?? null;
            if (\is_string($md5ext) && !$assets->has($md5ext)) {
                $errors[] = new ValidationError(
                    ErrorCode::MISSING_ASSET,
                    new ValidationPath(\sprintf('%s.costumes[%d].md5ext', $targetPath, $index), $targetIndex),
                    \sprintf('Costume asset "%s" not found in asset index', $md5ext),
                );
            }
        }

        foreach (($target['sounds'] ?? []) as $index => $sound) {
            $md5ext = $sound['md5ext'] ?? null;
            if (\is_string($md5ext) && !$assets->has($md5ext)) {
                $errors[] = new ValidationError(
                    ErrorCode::MISSING_ASSET,
                    new ValidationPath(\sprintf('%s.sounds[%d].md5ext', $targetPath, $index), $targetIndex),
                    \sprintf('Sound asset "%s" not found in asset index', $md5ext),
                );
            }
        }
    }
}
