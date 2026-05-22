<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Validator\Rule;

use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationError;

/**
 * A user-supplied validation rule, plugged into {@see \CodeClubLuxembourg\Sb3Toolkit\Validator\ScratchValidator}
 * via {@see \CodeClubLuxembourg\Sb3Toolkit\Validator\ScratchValidator::withRule()}.
 *
 * Rules return `ValidationError`s instead of writing into an out-parameter so
 * they compose cleanly with generators and `array_map` / `array_filter`.
 *
 * The built-in rules (`StructureRule`, `BlocksRule`, `AssetsRule`,
 * `VariablesRule`) don't implement this interface because they take extra
 * per-target context the validator threads in directly; this contract exists
 * for external rules that consume the whole project.
 */
interface Rule
{
    /**
     * @return iterable<ValidationError>
     */
    public function validate(ScratchProject $project): iterable;
}
