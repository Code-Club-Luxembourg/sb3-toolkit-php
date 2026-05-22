<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder\Field;

/**
 * Options for the `event_whengreaterthan` block's `WHENGREATERTHANMENU` field.
 */
enum GreaterThanMenu: string
{
    case Loudness = 'LOUDNESS';
    case Timer = 'TIMER';
}
