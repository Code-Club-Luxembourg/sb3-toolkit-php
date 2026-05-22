<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder;

/**
 * A reference to a Scratch variable (its display name plus generated id).
 *
 * Returned by {@see \CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject::addVariable()} and
 * {@see Sprite::addVariable()}, and accepted by every Data factory and by
 * {@see Input::variable()}, so name and id never drift out of sync.
 */
final readonly class VariableRef
{
    public function __construct(
        public string $name,
        public string $id,
    ) {}
}
