<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder\Field;

/**
 * Options for the `sound_changeeffectby` / `sound_seteffectto` `EFFECT` field.
 */
enum SoundEffect: string
{
    case Pitch = 'PITCH';
    case Pan = 'PAN';
}
