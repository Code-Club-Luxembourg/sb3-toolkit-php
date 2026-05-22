<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Validator;

/**
 * Machine-readable validation error codes.
 *
 * The string value of each case is the stable identifier consumers should
 * compare against (e.g. in logs or external systems). Enum cases give
 * IDE autocompletion and exhaustiveness checking via `match`.
 */
enum ErrorCode: string
{
    case MISSING_FIELD = 'MISSING_FIELD';
    case WRONG_TYPE = 'WRONG_TYPE';
    case MISSING_STAGE = 'MISSING_STAGE';
    case DUPLICATE_STAGE = 'DUPLICATE_STAGE';
    case UNKNOWN_OPCODE = 'UNKNOWN_OPCODE';
    case MISSING_INPUT = 'MISSING_INPUT';
    case MISSING_FIELD_VALUE = 'MISSING_FIELD_VALUE';
    case DANGLING_NEXT = 'DANGLING_NEXT';
    case DANGLING_INPUT_REF = 'DANGLING_INPUT_REF';
    case DANGLING_PARENT = 'DANGLING_PARENT';
    case INCONSISTENT_NEXT_PARENT = 'INCONSISTENT_NEXT_PARENT';
    case BLOCK_CYCLE = 'BLOCK_CYCLE';
    case MISSING_ASSET = 'MISSING_ASSET';
    case DANGLING_VARIABLE_REF = 'DANGLING_VARIABLE_REF';
    case DANGLING_LIST_REF = 'DANGLING_LIST_REF';
    case TOPLEVEL_NO_COORDS = 'TOPLEVEL_NO_COORDS';
    case SHADOW_NO_PARENT = 'SHADOW_NO_PARENT';
    case ORPHANED_BLOCK = 'ORPHANED_BLOCK';
    case UNUSED_VARIABLE = 'UNUSED_VARIABLE';
    case UNUSED_LIST = 'UNUSED_LIST';
}
