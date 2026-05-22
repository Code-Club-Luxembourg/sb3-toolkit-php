<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Cli\Packager;

/**
 * Parameters for packaging a project into a standalone HTML file.
 */
final class PackRequest
{
    public function __construct(
        public readonly string $projectPath,
        public readonly string $outputPath,
        public readonly int $stageWidth,
        public readonly int $stageHeight,
        public readonly bool $autoplay = false,
        public readonly ?string $customJs = null,
        public readonly ?int $countdownSeconds = null,
        public readonly ?string $checksumFile = null,
        public readonly ?string $checksumUrl = null,
    ) {}
}
