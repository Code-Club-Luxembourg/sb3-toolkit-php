<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder\Field;

/**
 * Options for the `looks_changeeffectby` / `looks_seteffectto` `EFFECT` field.
 */
enum GraphicEffect: string
{
    case Color = 'COLOR';
    case Fisheye = 'FISHEYE';
    case Whirl = 'WHIRL';
    case Pixelate = 'PIXELATE';
    case Mosaic = 'MOSAIC';
    case Brightness = 'BRIGHTNESS';
    case Ghost = 'GHOST';
}
