<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Validator;

use Stringable;

/**
 * The location of a validation finding within a project.
 *
 * `$path` is the dot/bracket notation used for human display (e.g.
 * `"targets[1].blocks.abc.inputs.STEPS"`). The structured fields let
 * consumers filter findings without string parsing:
 *
 * - `$targetIndex` — which target the finding belongs to (null for
 *   project-level paths such as `meta.semver` or `targets`).
 * - `$blockId` — which block, when the finding is inside a block.
 *
 * `__toString()` returns `$path`, so existing string-formatting (`printf`,
 * concatenation) keeps working.
 */
final class ValidationPath implements Stringable
{
    public function __construct(
        public readonly string $path,
        public readonly ?int $targetIndex = null,
        public readonly ?string $blockId = null,
    ) {}

    public function __toString(): string
    {
        return $this->path;
    }
}
