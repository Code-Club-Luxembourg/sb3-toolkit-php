<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder;

/**
 * A reference to a Scratch broadcast message (its display name plus id).
 *
 * Accepted by {@see Input::broadcast()} so name and id stay in sync.
 */
final readonly class BroadcastRef
{
    public function __construct(
        public string $name,
        public string $id,
    ) {}
}
