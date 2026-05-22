<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder;

/**
 * A script (top-level block) together with the target it belongs to.
 *
 * Yielded by {@see \CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject::findAllScripts()}.
 * `$target` is either a {@see Sprite} or a {@see Stage}; `$script` is a
 * {@see BlockRef} pointing at the hat / root block.
 */
final readonly class ScriptLocation
{
    public function __construct(
        public Scriptable $target,
        public BlockRef $script,
    ) {}
}
