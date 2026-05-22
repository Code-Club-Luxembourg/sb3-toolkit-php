<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Studio;

/**
 * A project downloaded as JSON plus its individually fetched assets, ready to
 * be re-assembled into an `.sb3` via {@see ScratchProject}.
 */
final class JsonDownload extends DownloadResult
{
    /**
     * @param array<string, mixed>  $project parsed `project.json` data
     * @param array<string, string> $assets  md5ext => binary contents
     */
    public function __construct(
        public readonly array $project,
        public readonly array $assets,
    ) {
        parent::__construct();
    }
}
