<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder\Field;

/**
 * Options for the `looks_costumenumbername` / `looks_backdropnumbername` `NUMBER_NAME` field.
 */
enum NumberName: string
{
    case Number = 'number';
    case Name = 'name';
}
