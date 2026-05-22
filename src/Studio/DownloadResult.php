<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Studio;

/**
 * Outcome of downloading a project. Exactly one of two concrete subclasses:
 * {@see RawDownload} (an opaque `.sb3` blob) or {@see JsonDownload} (parsed
 * project JSON plus individually fetched assets).
 *
 * Discriminate with `instanceof` — the typed subclass fields are non-nullable,
 * so the compiler can prove which payload is present:
 *
 * ```php
 * $result = $downloader->downloadProject($id, 0);
 * match (true) {
 *     $result instanceof RawDownload => file_put_contents($path, $result->sb3),
 *     $result instanceof JsonDownload => buildSb3($result->project, $result->assets),
 * };
 * ```
 */
abstract class DownloadResult
{
    /**
     * Marked `@internal` so the base remains effectively sealed: only the two
     * shipped subclasses should extend it.
     *
     * @internal
     */
    protected function __construct() {}

    public static function raw(string $rawSb3): RawDownload
    {
        return new RawDownload($rawSb3);
    }

    /**
     * @param array<string, mixed>  $project
     * @param array<string, string> $assets md5ext => binary contents
     */
    public static function json(array $project, array $assets): JsonDownload
    {
        return new JsonDownload($project, $assets);
    }
}
