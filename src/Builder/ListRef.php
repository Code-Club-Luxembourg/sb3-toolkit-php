<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder;

/**
 * A reference to a Scratch list (its display name plus generated id).
 *
 * Returned by {@see \CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject::addList()} and
 * {@see Sprite::addList()}, and accepted by every Data list factory and by
 * {@see Input::list()}, so name and id never drift out of sync.
 */
final readonly class ListRef
{
    public function __construct(
        public string $name,
        public string $id,
    ) {}
}
