<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Studio;

/**
 * A project downloaded as an opaque `.sb3` zip blob. Write `->sb3` directly.
 */
final class RawDownload extends DownloadResult
{
    public function __construct(public readonly string $sb3)
    {
        parent::__construct();
    }
}
