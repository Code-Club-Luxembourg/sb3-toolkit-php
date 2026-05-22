<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder\Field;

/**
 * Options for the `control_stop` block's `STOP_OPTION` field.
 */
enum StopOption: string
{
    case All = 'all';
    case ThisScript = 'this script';
    case OtherScriptsInSprite = 'other scripts in sprite';
}
