<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Studio;

use DateTimeImmutable;
use Exception;
use Generator;
use JsonException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchAssetIndex;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;

/**
 * Downloads Scratch projects and studios via the {@see ScratchApi}.
 */
final class StudioDownloader
{
    private readonly LoggerInterface $log;

    public function __construct(
        private readonly ScratchApi $api = new ScratchApi(),
        ?LoggerInterface $log = null,
    ) {
        $this->log = $log ?? new NullLogger();
    }

    /**
     * Stream project entries from a studio.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function iterStudioProjects(string $studioId, int $limit, float $delay): Generator
    {
        $offset = 0;
        while (true) {
            $url = \sprintf(
                '%s?limit=%d&offset=%d',
                ScratchApi::formatUrl(ScratchApi::STUDIO_PROJECTS_URL, ['studio_id' => $studioId]),
                $limit,
                $offset,
            );
            $batch = $this->api->requestJson($url);
            if (!\is_array($batch) || $batch === []) {
                return;
            }
            foreach ($batch as $item) {
                yield $item;
            }
            $offset += $limit;
            $this->api->delaySeconds($delay);
        }
    }

    public static function targetPath(string $outputDir, int $projectId): string
    {
        return $outputDir . \DIRECTORY_SEPARATOR . $projectId . '.sb3';
    }

    public static function metadataPath(string $outputDir, int $projectId): string
    {
        return $outputDir . \DIRECTORY_SEPARATOR . $projectId . '.meta.json';
    }

    /**
     * Fetch the metadata for a project.
     *
     * @return array<string, mixed>
     */
    public function fetchProjectMetadata(int $projectId): array
    {
        $meta = $this->api->requestJson(ScratchApi::formatUrl(ScratchApi::PROJECT_META_URL, ['project_id' => $projectId]));

        return \is_array($meta) ? $meta : [];
    }

    /**
     * Read the locally stored metadata sidecar, or null when absent.
     *
     * @return array<string, mixed>|null
     */
    public static function readStoredProjectMetadata(string $outputDir, int $projectId): ?array
    {
        $filePath = self::metadataPath($outputDir, $projectId);
        if (!is_file($filePath)) {
            return null;
        }
        $raw = file_get_contents($filePath);
        if ($raw === false) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * Return true when the remote project's history.modified timestamp is no
     * newer than the stored copy's.
     *
     * Project tokens are short-lived signed-URL credentials that rotate on
     * every metadata request, so they are deliberately not used as a freshness
     * signal. If either side lacks a parseable `history.modified` timestamp,
     * the project is treated as out-of-date so a fresh download is forced.
     *
     * @param array<string, mixed>|null $stored
     * @param array<string, mixed>      $remote
     */
    public static function isProjectUpToDate(?array $stored, array $remote): bool
    {
        $storedTime = self::parseTimestamp($stored['historyModified'] ?? null);
        $remoteTime = self::parseTimestamp($remote['history']['modified'] ?? null);
        if (!$storedTime instanceof DateTimeImmutable || !$remoteTime instanceof DateTimeImmutable) {
            return false;
        }

        return $remoteTime <= $storedTime;
    }

    /**
     * Persist project metadata alongside the downloaded `.sb3` file.
     *
     * Always writes a sidecar (even when `history.modified` is missing) so the
     * download is recorded; the project token is never persisted because it is
     * a short-lived credential.
     *
     * @param array<string, mixed> $metadata
     */
    public static function writeProjectMetadata(string $outputDir, int $projectId, array $metadata): string
    {
        $historyModified = $metadata['history']['modified'] ?? null;
        $stored = [
            'historyModified' => \is_string($historyModified) ? $historyModified : null,
            'downloadedAt' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        $filePath = self::metadataPath($outputDir, $projectId);
        $contents = json_encode($stored, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR) . "\n";
        self::writeFileAtomic($filePath, $contents);

        return $filePath;
    }

    private static function parseTimestamp(mixed $value): ?DateTimeImmutable
    {
        if (!\is_string($value) || $value === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Write a file by staging it to `.tmp` and atomically renaming, so a killed
     * process cannot leave half-written contents at the target path.
     */
    private static function writeFileAtomic(string $filePath, string $contents): void
    {
        $tmpPath = $filePath . '.tmp';
        if (file_put_contents($tmpPath, $contents) === false) {
            throw new RuntimeException(\sprintf('Failed to write temp file: %s', $tmpPath));
        }
        if (!rename($tmpPath, $filePath)) {
            @unlink($tmpPath);
            throw new RuntimeException(\sprintf('Failed to move temp file into place: %s', $filePath));
        }
    }

    /**
     * Download a project as raw `.sb3` or as JSON plus individually fetched assets.
     *
     * @param array<string, mixed>|null $meta
     */
    public function downloadProject(int $projectId, float $delay, ?array $meta = null): DownloadResult
    {
        $resolvedMeta = $meta ?? $this->fetchProjectMetadata($projectId);
        $token = $resolvedMeta['project_token'] ?? null;
        if (!\is_string($token) || $token === '') {
            throw new RuntimeException('Missing project_token in metadata response.');
        }

        $raw = $this->api->requestBytes(
            ScratchApi::formatUrl(ScratchApi::PROJECT_DOWNLOAD_URL, ['project_id' => $projectId, 'token' => $token]),
        );

        if ($this->isJsonBuffer($raw)) {
            try {
                /** @var array<string, mixed> $project */
                $project = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
            } catch (JsonException $error) {
                throw new RuntimeException(\sprintf('Failed to parse project JSON for %d: %s', $projectId, $error->getMessage()), 0, $error);
            }
            $assets = $this->downloadAssets($this->collectAssetIds($project));

            return DownloadResult::json($project, $assets);
        }

        return DownloadResult::raw($raw);
    }

    /**
     * Download a project, optionally skipping unchanged ones, and write to disk.
     *
     * @return 'skipped'|'downloaded'
     */
    public function downloadAndSaveProject(string $outputDir, int $projectId, float $delay, bool $skipExisting): string
    {
        $target = self::targetPath($outputDir, $projectId);
        $metadata = $this->fetchProjectMetadata($projectId);

        if ($skipExisting && is_file($target)) {
            $stored = self::readStoredProjectMetadata($outputDir, $projectId);
            if (self::isProjectUpToDate($stored, $metadata)) {
                $this->log->info('Skipping project {id} (unchanged) -> {path}', [
                    'id' => $projectId,
                    'path' => $target,
                ]);

                return 'skipped';
            }
        }

        $result = $this->downloadProject($projectId, $delay, $metadata);
        $outputPath = self::writeProject($outputDir, $projectId, $result);
        self::writeProjectMetadata($outputDir, $projectId, $metadata);
        $this->log->info('Downloaded project {id} -> {path}', [
            'id' => $projectId,
            'path' => $outputPath,
        ]);

        return 'downloaded';
    }

    /**
     * Persist a downloaded project to disk and return the output path.
     */
    public static function writeProject(string $outputDir, int $projectId, DownloadResult $result): string
    {
        $outputPath = self::targetPath($outputDir, $projectId);

        if ($result instanceof RawDownload) {
            self::writeFileAtomic($outputPath, $result->sb3);

            return $outputPath;
        }

        \assert($result instanceof JsonDownload);

        $assetIndex = new ScratchAssetIndex();
        foreach ($result->assets as $md5ext => $data) {
            $assetIndex->add($md5ext, $data);
        }

        // saveToSb3File overwrites in place; stage via a temp path and rename
        // so a killed process leaves the previous .sb3 (if any) intact.
        $tmpPath = $outputPath . '.tmp';
        $project = new ScratchProject($result->project, $assetIndex);
        $project->saveToSb3File($tmpPath);
        if (!rename($tmpPath, $outputPath)) {
            @unlink($tmpPath);
            throw new RuntimeException(\sprintf('Failed to move temp .sb3 into place: %s', $outputPath));
        }

        return $outputPath;
    }

    /**
     * Collect unique asset md5ext references from a project.
     *
     * @param array<string, mixed> $project
     *
     * @return list<string>
     */
    private function collectAssetIds(array $project): array
    {
        $assetIds = [];
        foreach (($project['targets'] ?? []) as $target) {
            foreach ([...($target['costumes'] ?? []), ...($target['sounds'] ?? [])] as $asset) {
                $md5ext = $asset['md5ext'] ?? null;
                if (\is_string($md5ext) && $md5ext !== '') {
                    $assetIds[$md5ext] = true;
                }
            }
        }

        return array_keys($assetIds);
    }

    /**
     * Fetch all referenced assets in parallel.
     *
     * The previous per-asset `$delay` is gone because requests now dispatch
     * concurrently — politeness is handled by the underlying client (HTTP/2
     * multiplexing, per-host connection cap) plus the API's retry strategy
     * for `429`/`Retry-After`. Failures aggregate so a single missing asset
     * does not lose the rest of the batch.
     *
     * @param list<string> $assetIds
     *
     * @return array<string, string>
     */
    private function downloadAssets(array $assetIds): array
    {
        if ($assetIds === []) {
            return [];
        }

        $urlByMd5 = [];
        foreach ($assetIds as $md5ext) {
            $urlByMd5[$md5ext] = ScratchApi::formatUrl(ScratchApi::ASSET_URL, ['md5ext' => $md5ext]);
        }

        $bodies = $this->api->requestBytesBatch(array_values($urlByMd5));

        $assets = [];
        foreach ($urlByMd5 as $md5ext => $url) {
            $assets[$md5ext] = $bodies[$url];
        }

        return $assets;
    }

    /**
     * Detect whether a blob is JSON by its first non-whitespace byte.
     */
    private function isJsonBuffer(string $buffer): bool
    {
        $length = \strlen($buffer);
        for ($i = 0; $i < $length; ++$i) {
            $byte = \ord($buffer[$i]);
            if (in_array($byte, [0x20, 0x0A, 0x0D, 0x09], true)) {
                continue;
            }

            return $byte === 0x7B;
        }

        return false;
    }
}
