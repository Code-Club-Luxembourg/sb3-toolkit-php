<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Validator;

/**
 * The inputs and fields a given opcode is expected to provide.
 */
final class OpcodeSpec
{
    /**
     * @param list<string> $requiredInputs
     * @param list<string> $requiredFields
     */
    public function __construct(
        public readonly array $requiredInputs = [],
        public readonly array $requiredFields = [],
    ) {}
}
