<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Validator;

/**
 * A single validation finding produced by one of the {@see Rule} classes.
 *
 * The path arrives as either a raw dot-notation string or a {@see ValidationPath};
 * a string is auto-wrapped with `targetIndex` and `blockId` left null, so legacy
 * call sites keep working while new code can attach structured metadata.
 */
final class ValidationError
{
    public readonly ValidationPath $path;

    public function __construct(
        public readonly ErrorCode $code,
        string|ValidationPath $path,
        public readonly string $message,
        public readonly ValidationSeverity $severity = ValidationSeverity::Error,
    ) {
        $this->path = $path instanceof ValidationPath ? $path : new ValidationPath($path);
    }
}
