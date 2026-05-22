<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder\Field;

/**
 * Options for the `motion_setrotationstyle` block's `STYLE` field.
 */
enum RotationStyle: string
{
    case AllAround = 'all around';
    case LeftRight = 'left-right';
    case DontRotate = "don't rotate";
}
