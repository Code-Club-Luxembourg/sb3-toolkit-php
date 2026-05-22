<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder\Field;

/**
 * Options for the `sensing_current` block's `CURRENTMENU` field.
 */
enum CurrentMenu: string
{
    case Year = 'YEAR';
    case Month = 'MONTH';
    case Date = 'DATE';
    case DayOfWeek = 'DAYOFWEEK';
    case Hour = 'HOUR';
    case Minute = 'MINUTE';
    case Second = 'SECOND';
}
