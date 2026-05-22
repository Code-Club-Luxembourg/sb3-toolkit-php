<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Cli\Packager;

/**
 * Packages a `.sb3` project into a standalone HTML player.
 */
interface PackagerInterface
{
    public function pack(PackRequest $request): void;
}
