<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Validator;

/**
 * The outcome of validating a project.
 */
final class ValidationResult
{
    /**
     * @param list<ValidationError> $errors
     */
    public function __construct(
        public readonly bool $isValid,
        public readonly array $errors,
    ) {}

    /**
     * Build a result, deriving validity from the absence of error-severity findings.
     *
     * @param list<ValidationError> $errors
     */
    public static function fromErrors(array $errors): self
    {
        $isValid = true;
        foreach ($errors as $error) {
            if ($error->severity === ValidationSeverity::Error) {
                $isValid = false;
                break;
            }
        }

        return new self($isValid, $errors);
    }
}
