<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Validator;

/**
 * Severity of a validation finding.
 */
enum ValidationSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
