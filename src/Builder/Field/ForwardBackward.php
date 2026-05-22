<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder\Field;

/**
 * Options for the `looks_goforwardbackwardlayers` block's `FORWARD_BACKWARD` field.
 */
enum ForwardBackward: string
{
    case Forward = 'forward';
    case Backward = 'backward';
}
