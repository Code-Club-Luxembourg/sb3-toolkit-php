<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder\Field;

/**
 * Options for the `looks_gotofrontback` block's `FRONT_BACK` field.
 */
enum FrontBack: string
{
    case Front = 'front';
    case Back = 'back';
}
