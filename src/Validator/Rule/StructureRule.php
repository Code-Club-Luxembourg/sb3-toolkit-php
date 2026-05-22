<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Validator\Rule;

use CodeClubLuxembourg\Sb3Toolkit\Validator\ErrorCode;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationError;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationPath;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationSeverity;

/**
 * Structural validation of the project and its targets.
 */
final class StructureRule
{
    private const array VALID_ROTATION_STYLES = ['all around', 'left-right', "don't rotate"];
    private const array VALID_VIDEO_STATES = ['on', 'off', 'on-flipped'];
    private const array VALID_COSTUME_FORMATS = ['svg', 'png', 'jpg', 'jpeg', 'bmp', 'gif'];
    private const array VALID_SOUND_FORMATS = ['wav', 'wave', 'mp3'];
    private const string SEMVER_RE = '/^\d+\.\d+\.\d+$/';

    /**
     * @param array<string, mixed>  $data
     * @param list<ValidationError> $errors
     */
    public static function validate(array $data, array &$errors): void
    {
        $targets = $data['targets'] ?? null;
        if (!\is_array($targets) || $targets === []) {
            $errors[] = self::error(ErrorCode::MISSING_FIELD, 'targets', null, 'targets must be a non-empty array');

            return;
        }

        $meta = $data['meta'] ?? null;
        $semver = \is_array($meta) ? ($meta['semver'] ?? null) : null;
        if (!\is_string($semver) || preg_match(self::SEMVER_RE, $semver) !== 1) {
            $errors[] = self::error(
                ErrorCode::WRONG_TYPE,
                'meta.semver',
                null,
                'meta.semver must be a semver string (e.g. "3.0.0")',
                ValidationSeverity::Warning,
            );
        }

        $stageCount = 0;
        foreach ($targets as $target) {
            if (\is_array($target) && self::isStage($target)) {
                ++$stageCount;
            }
        }
        if ($stageCount === 0) {
            $errors[] = self::error(ErrorCode::MISSING_STAGE, 'targets', null, 'Project must have exactly one stage target');
        } elseif ($stageCount > 1) {
            $errors[] = self::error(
                ErrorCode::DUPLICATE_STAGE,
                'targets',
                null,
                \sprintf('Project has %d stage targets; expected exactly one', $stageCount),
            );
        }

        foreach ($targets as $index => $target) {
            $path = \sprintf('targets[%d]', $index);
            if (!\is_array($target)) {
                $errors[] = self::error(ErrorCode::WRONG_TYPE, $path, $index, 'Target must be an object');

                continue;
            }
            self::validateTarget($target, $index, $path, $errors);
        }
    }

    /**
     * @param array<string, mixed>  $target
     * @param list<ValidationError> $errors
     */
    private static function validateTarget(array $target, int $targetIndex, string $path, array &$errors): void
    {
        $name = $target['name'] ?? null;
        if (!\is_string($name) || $name === '') {
            $errors[] = self::error(ErrorCode::MISSING_FIELD, "{$path}.name", $targetIndex, 'Target name must be a non-empty string');
        }

        if (\array_key_exists('isStage', $target) && !\is_bool($target['isStage'])) {
            $errors[] = self::error(ErrorCode::WRONG_TYPE, "{$path}.isStage", $targetIndex, 'isStage must be a boolean');
        }

        foreach (['variables', 'lists', 'broadcasts', 'blocks', 'comments'] as $field) {
            if (!\is_array($target[$field] ?? null)) {
                $errors[] = self::error(ErrorCode::WRONG_TYPE, "{$path}.{$field}", $targetIndex, "{$field} must be an object");
            }
        }

        $volume = $target['volume'] ?? null;
        if (!self::isNumber($volume)) {
            $errors[] = self::error(ErrorCode::WRONG_TYPE, "{$path}.volume", $targetIndex, 'volume must be a number');
        } elseif ($volume < 0) {
            $errors[] = self::error(ErrorCode::WRONG_TYPE, "{$path}.volume", $targetIndex, 'volume must be non-negative');
        }

        self::validateCostumes($target, $targetIndex, $path, $errors);
        self::validateSounds($target, $targetIndex, $path, $errors);

        $videoState = $target['videoState'] ?? null;
        if ($videoState !== null && !\in_array($videoState, self::VALID_VIDEO_STATES, true)) {
            $errors[] = self::error(ErrorCode::WRONG_TYPE, "{$path}.videoState", $targetIndex, 'videoState must be one of: on, off, on-flipped');
        }

        if (!self::isStage($target)) {
            self::validateSpriteFields($target, $targetIndex, $path, $errors);
        }
    }

    /**
     * @param array<string, mixed>  $target
     * @param list<ValidationError> $errors
     */
    private static function validateCostumes(array $target, int $targetIndex, string $path, array &$errors): void
    {
        $costumes = $target['costumes'] ?? null;
        if (!\is_array($costumes)) {
            $errors[] = self::error(ErrorCode::WRONG_TYPE, "{$path}.costumes", $targetIndex, 'costumes must be an array');

            return;
        }

        if ($costumes === []) {
            $errors[] = self::error(ErrorCode::MISSING_FIELD, "{$path}.costumes", $targetIndex, 'Target must have at least one costume');
        } else {
            $current = $target['currentCostume'] ?? null;
            if (!\is_int($current)) {
                $errors[] = self::error(
                    ErrorCode::WRONG_TYPE,
                    "{$path}.currentCostume",
                    $targetIndex,
                    'currentCostume must be an integer',
                );
            } elseif ($current < 0 || $current >= \count($costumes)) {
                $errors[] = self::error(
                    ErrorCode::WRONG_TYPE,
                    "{$path}.currentCostume",
                    $targetIndex,
                    \sprintf('currentCostume %d is out of range (0..%d)', $current, \count($costumes) - 1),
                );
            }
        }

        foreach ($costumes as $index => $costume) {
            self::validateAssetEntry(
                $costume,
                'Costume',
                \sprintf('%s.costumes[%d]', $path, $index),
                $targetIndex,
                self::VALID_COSTUME_FORMATS,
                ['rotationCenterX', 'rotationCenterY'],
                $errors,
            );
        }
    }

    /**
     * @param array<string, mixed>  $target
     * @param list<ValidationError> $errors
     */
    private static function validateSounds(array $target, int $targetIndex, string $path, array &$errors): void
    {
        $sounds = $target['sounds'] ?? null;
        if (!\is_array($sounds)) {
            $errors[] = self::error(ErrorCode::WRONG_TYPE, "{$path}.sounds", $targetIndex, 'sounds must be an array');

            return;
        }

        foreach ($sounds as $index => $sound) {
            self::validateAssetEntry(
                $sound,
                'Sound',
                \sprintf('%s.sounds[%d]', $path, $index),
                $targetIndex,
                self::VALID_SOUND_FORMATS,
                ['rate', 'sampleCount'],
                $errors,
                requireNonNegativeNumbers: true,
            );
        }
    }

    /**
     * @param list<string>          $validFormats
     * @param list<string>          $numberFields
     * @param list<ValidationError> $errors
     */
    private static function validateAssetEntry(
        mixed $entry,
        string $kind,
        string $path,
        int $targetIndex,
        array $validFormats,
        array $numberFields,
        array &$errors,
        bool $requireNonNegativeNumbers = false,
    ): void {
        if (!\is_array($entry)) {
            $errors[] = self::error(ErrorCode::WRONG_TYPE, $path, $targetIndex, "{$kind} must be an object");

            return;
        }

        foreach (['name', 'assetId', 'md5ext', 'dataFormat'] as $field) {
            if (!\is_string($entry[$field] ?? null)) {
                $errors[] = self::error(ErrorCode::MISSING_FIELD, "{$path}.{$field}", $targetIndex, "{$kind} {$field} must be a string");
            }
        }

        $dataFormat = $entry['dataFormat'] ?? null;
        if (\is_string($dataFormat) && !\in_array(strtolower($dataFormat), $validFormats, true)) {
            $errors[] = self::error(
                ErrorCode::WRONG_TYPE,
                "{$path}.dataFormat",
                $targetIndex,
                \sprintf('%s dataFormat "%s" must be one of: %s', $kind, $dataFormat, implode(', ', $validFormats)),
            );
        }

        foreach ($numberFields as $field) {
            $value = $entry[$field] ?? null;
            if (!self::isNumber($value)) {
                $errors[] = self::error(ErrorCode::MISSING_FIELD, "{$path}.{$field}", $targetIndex, "{$kind} {$field} must be a number");
            } elseif ($requireNonNegativeNumbers && $value < 0) {
                $errors[] = self::error(ErrorCode::WRONG_TYPE, "{$path}.{$field}", $targetIndex, "{$kind} {$field} must be non-negative");
            }
        }
    }

    /**
     * @param array<string, mixed>  $target
     * @param list<ValidationError> $errors
     */
    private static function validateSpriteFields(array $target, int $targetIndex, string $path, array &$errors): void
    {
        foreach (['x', 'y', 'size', 'direction'] as $field) {
            if (!self::isFiniteNumber($target[$field] ?? null)) {
                $errors[] = self::error(ErrorCode::WRONG_TYPE, "{$path}.{$field}", $targetIndex, "{$field} must be a finite number");
            }
        }
        if (!\is_bool($target['visible'] ?? null)) {
            $errors[] = self::error(ErrorCode::WRONG_TYPE, "{$path}.visible", $targetIndex, 'visible must be a boolean');
        }
        $layerOrder = $target['layerOrder'] ?? null;
        if (!\is_int($layerOrder) || $layerOrder < 1) {
            $errors[] = self::error(ErrorCode::WRONG_TYPE, "{$path}.layerOrder", $targetIndex, 'layerOrder must be a positive integer');
        }
        if (!\in_array($target['rotationStyle'] ?? null, self::VALID_ROTATION_STYLES, true)) {
            $errors[] = self::error(
                ErrorCode::WRONG_TYPE,
                "{$path}.rotationStyle",
                $targetIndex,
                'rotationStyle must be one of: "all around", "left-right", "don\'t rotate"',
            );
        }
    }

    /**
     * @param array<string, mixed> $target
     */
    private static function isStage(array $target): bool
    {
        return ($target['isStage'] ?? null) === true;
    }

    private static function isNumber(mixed $value): bool
    {
        return \is_int($value) || \is_float($value);
    }

    private static function isFiniteNumber(mixed $value): bool
    {
        return self::isNumber($value) && is_finite((float) $value);
    }

    private static function error(
        ErrorCode $code,
        string $path,
        ?int $targetIndex,
        string $message,
        ValidationSeverity $severity = ValidationSeverity::Error,
    ): ValidationError {
        return new ValidationError($code, new ValidationPath($path, $targetIndex), $message, $severity);
    }
}
