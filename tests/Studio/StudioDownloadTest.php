<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Studio;

use PHPUnit\Framework\TestCase;
use CodeClubLuxembourg\Sb3Toolkit\Studio\DownloadResult;
use CodeClubLuxembourg\Sb3Toolkit\Studio\JsonDownload;
use CodeClubLuxembourg\Sb3Toolkit\Studio\RawDownload;
use CodeClubLuxembourg\Sb3Toolkit\Studio\ScratchApi;
use CodeClubLuxembourg\Sb3Toolkit\Studio\ScratchApiException;
use CodeClubLuxembourg\Sb3Toolkit\Studio\StudioDownloader;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class StudioDownloadTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/scratch-studio-' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function downloader(array $responses): StudioDownloader
    {
        return new StudioDownloader($this->api($responses));
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function api(array $responses): ScratchApi
    {
        // autoRetry: false — MockHttpClient consumes one response per call, so a
        // retryable status would silently swallow extra responses we set up.
        return new ScratchApi(new MockHttpClient($responses), autoRetry: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function projectData(): array
    {
        return [
            'targets' => [[
                'isStage' => true,
                'name' => 'Stage',
                'variables' => [],
                'lists' => [],
                'broadcasts' => [],
                'blocks' => [],
                'comments' => [],
                'currentCostume' => 0,
                'costumes' => [['name' => 'stage-costume', 'dataFormat' => 'svg', 'assetId' => 'stage-costume', 'md5ext' => 'stage-costume.svg', 'rotationCenterX' => 0, 'rotationCenterY' => 0]],
                'sounds' => [['name' => 'stage-sound', 'dataFormat' => 'wav', 'assetId' => 'stage-sound', 'md5ext' => 'stage-sound.wav', 'rate' => 44100, 'sampleCount' => 1]],
                'volume' => 100,
            ]],
            'monitors' => [],
            'extensions' => [],
            'meta' => ['semver' => '3.0.0', 'vm' => '0.2.0'],
        ];
    }

    public function testReturnsRawSb3WhenDownloadIsNotJson(): void
    {
        $downloader = $this->downloader([
            new MockResponse(json_encode(['project_token' => 'token'])),
            new MockResponse("PK\x03\x04"),
        ]);

        $result = $downloader->downloadProject(123, 0);

        self::assertInstanceOf(RawDownload::class, $result);
        self::assertSame("PK\x03\x04", $result->sb3);
    }

    public function testReturnsProjectJsonAndAssetsWhenDownloadIsJson(): void
    {
        $downloader = $this->downloader([
            new MockResponse(json_encode(['project_token' => 'token'])),
            new MockResponse(json_encode($this->projectData())),
            new MockResponse('costume'),
            new MockResponse('sound'),
        ]);

        $result = $downloader->downloadProject(999, 0);

        self::assertInstanceOf(JsonDownload::class, $result);
        self::assertCount(1, $result->project['targets']);
        self::assertCount(2, $result->assets);
    }

    public function testWritesRawSb3Directly(): void
    {
        $output = StudioDownloader::writeProject($this->tmpDir, 1, DownloadResult::raw('sb3-bytes'));
        self::assertStringContainsString('1.sb3', $output);
        self::assertSame('sb3-bytes', file_get_contents($output));
    }

    public function testReadsStoredProjectMetadataWhenPresent(): void
    {
        $stored = ['historyModified' => '2026-03-15T10:00:00Z', 'downloadedAt' => '2026-03-15T10:05:00Z'];
        file_put_contents(StudioDownloader::metadataPath($this->tmpDir, 7), json_encode($stored));

        self::assertSame($stored, StudioDownloader::readStoredProjectMetadata($this->tmpDir, 7));
    }

    public function testReadsNullWhenMetadataAbsent(): void
    {
        self::assertNull(StudioDownloader::readStoredProjectMetadata($this->tmpDir, 404));
    }

    public function testWritesProjectMetadataSidecar(): void
    {
        $output = StudioDownloader::writeProjectMetadata($this->tmpDir, 7, [
            'project_token' => 'should-not-be-persisted',
            'history' => ['modified' => '2026-03-15T10:00:00Z'],
        ]);

        self::assertStringContainsString('7.meta.json', $output);
        $contents = (string) file_get_contents($output);
        self::assertStringContainsString('"historyModified": "2026-03-15T10:00:00Z"', $contents);
        self::assertStringContainsString('"downloadedAt"', $contents);
        self::assertStringNotContainsString('projectToken', $contents);
        self::assertStringNotContainsString('should-not-be-persisted', $contents);
    }

    public function testWritesProjectMetadataSidecarEvenWithoutHistoryModified(): void
    {
        $output = StudioDownloader::writeProjectMetadata($this->tmpDir, 8, []);

        $decoded = json_decode((string) file_get_contents($output), true);
        self::assertIsArray($decoded);
        self::assertNull($decoded['historyModified']);
        self::assertIsString($decoded['downloadedAt']);
    }

    public function testEqualRemoteTimestampsAreUpToDate(): void
    {
        self::assertTrue(StudioDownloader::isProjectUpToDate(
            ['historyModified' => '2026-03-15T10:00:00Z'],
            ['history' => ['modified' => '2026-03-15T10:00:00Z']],
        ));
    }

    public function testNewerRemoteTimestampsAreChanged(): void
    {
        self::assertFalse(StudioDownloader::isProjectUpToDate(
            ['historyModified' => '2026-03-15T10:00:00Z'],
            ['history' => ['modified' => '2026-03-15T10:06:00Z']],
        ));
    }

    public function testProjectTokensAreNotUsedForFreshness(): void
    {
        // Tokens rotate per request; they must not flip the freshness verdict.
        self::assertTrue(StudioDownloader::isProjectUpToDate(
            ['historyModified' => '2026-03-15T10:00:00Z', 'projectToken' => 'old-token'],
            ['project_token' => 'fresh-token-each-call', 'history' => ['modified' => '2026-03-15T10:00:00Z']],
        ));
    }

    public function testMissingHistoryModifiedForcesRedownload(): void
    {
        self::assertFalse(StudioDownloader::isProjectUpToDate(
            ['historyModified' => '2026-03-15T10:00:00Z'],
            ['history' => []],
        ));
        self::assertFalse(StudioDownloader::isProjectUpToDate(
            null,
            ['history' => ['modified' => '2026-03-15T10:00:00Z']],
        ));
    }

    public function testUnparseableTimestampForcesRedownload(): void
    {
        self::assertFalse(StudioDownloader::isProjectUpToDate(
            ['historyModified' => 'definitely-not-a-date'],
            ['history' => ['modified' => '2026-03-15T10:00:00Z']],
        ));
    }

    public function testIterStudioProjectsStopsOnEmptyBatch(): void
    {
        $downloader = $this->downloader([
            new MockResponse(json_encode([['id' => 1, 'title' => 'A'], ['id' => 2, 'title' => 'B']])),
            new MockResponse(json_encode([])),
        ]);

        $items = iterator_to_array($downloader->iterStudioProjects('555', 40, 0), false);
        self::assertCount(2, $items);
        self::assertSame(1, $items[0]['id']);
    }

    public function test404ResponseThrowsTypedExceptionWithStatus(): void
    {
        $api = $this->api([
            new MockResponse('not found', ['http_code' => 404]),
        ]);

        try {
            $api->requestJson('https://api.scratch.mit.edu/projects/999');
            self::fail('expected ScratchApiException');
        } catch (ScratchApiException $e) {
            self::assertSame(404, $e->statusCode);
            self::assertStringContainsString('999', $e->url);
            self::assertSame('not found', $e->bodySnippet);
            self::assertNull($e->retryAfterSeconds);
        }
    }

    public function test429ResponseParsesRetryAfterHeader(): void
    {
        $api = $this->api([
            new MockResponse('rate limited', ['http_code' => 429, 'response_headers' => ['Retry-After: 17']]),
        ]);

        try {
            $api->requestJson('https://api.scratch.mit.edu/projects/123');
            self::fail('expected ScratchApiException');
        } catch (ScratchApiException $e) {
            self::assertSame(429, $e->statusCode);
            self::assertSame(17, $e->retryAfterSeconds);
        }
    }

    public function testRetryableClientRetriesOnTransient5xx(): void
    {
        // Default constructor (autoRetry on); first call fails 503, second succeeds.
        $api = new ScratchApi(new MockHttpClient([
            new MockResponse('temporarily unavailable', ['http_code' => 503, 'response_headers' => ['Retry-After: 0']]),
            new MockResponse(json_encode(['ok' => true])),
        ]));

        $decoded = $api->requestJson('https://api.scratch.mit.edu/projects/1');
        self::assertSame(['ok' => true], $decoded);
    }

    public function testRequestBytesBatchReturnsBodiesKeyedByUrl(): void
    {
        $api = $this->api([
            new MockResponse('body-a'),
            new MockResponse('body-b'),
            new MockResponse('body-c'),
        ]);

        $bodies = $api->requestBytesBatch([
            'https://example.com/a',
            'https://example.com/b',
            'https://example.com/c',
        ]);

        self::assertSame('body-a', $bodies['https://example.com/a']);
        self::assertSame('body-b', $bodies['https://example.com/b']);
        self::assertSame('body-c', $bodies['https://example.com/c']);
    }

    public function testRequestBytesBatchAggregatesFailureUrls(): void
    {
        $api = $this->api([
            new MockResponse('ok'),
            new MockResponse('gone', ['http_code' => 404]),
            new MockResponse('boom', ['http_code' => 500]),
        ]);

        try {
            $api->requestBytesBatch([
                'https://example.com/a',
                'https://example.com/b',
                'https://example.com/c',
            ]);
            self::fail('expected ScratchApiException');
        } catch (ScratchApiException $e) {
            // Combined message must name every failing URL, not just the first.
            self::assertStringContainsString('https://example.com/b', $e->getMessage());
            self::assertStringContainsString('https://example.com/c', $e->getMessage());
            self::assertStringContainsString('2 concurrent requests failed', $e->getMessage());
        }
    }

    public function testStudioDownloadFetchesAssetsInParallel(): void
    {
        // 1 metadata + 1 project json + 2 assets = 4 responses.
        // The assets are dispatched in parallel; we just assert the bodies
        // end up in the right slots regardless of dispatch order.
        $downloader = $this->downloader([
            new MockResponse(json_encode(['project_token' => 'token'])),
            new MockResponse(json_encode($this->projectData())),
            new MockResponse('costume-bytes'),
            new MockResponse('sound-bytes'),
        ]);

        $result = $downloader->downloadProject(999, 0);
        self::assertInstanceOf(JsonDownload::class, $result);
        self::assertSame('costume-bytes', $result->assets['stage-costume.svg']);
        self::assertSame('sound-bytes', $result->assets['stage-sound.wav']);
    }

    public function testStreamToFileWritesAtomicallyToDest(): void
    {
        $api = $this->api([new MockResponse(str_repeat('AB', 1024))]);
        $dest = $this->tmpDir . '/streamed.bin';

        $api->streamToFile('https://example.com/big.bin', $dest);

        self::assertFileExists($dest);
        self::assertSame(str_repeat('AB', 1024), (string) file_get_contents($dest));
        self::assertFileDoesNotExist($dest . '.tmp');
    }

    public function testStreamToFileLeavesNoPartialFileOnFailure(): void
    {
        $api = $this->api([new MockResponse('not found', ['http_code' => 404])]);
        $dest = $this->tmpDir . '/streamed-fail.bin';

        try {
            $api->streamToFile('https://example.com/missing', $dest);
            self::fail('expected ScratchApiException');
        } catch (ScratchApiException $e) {
            self::assertSame(404, $e->statusCode);
        }

        self::assertFileDoesNotExist($dest);
        self::assertFileDoesNotExist($dest . '.tmp');
    }

    public function testFormatUrlEncodesReservedCharactersInTokens(): void
    {
        $url = ScratchApi::formatUrl(
            ScratchApi::PROJECT_DOWNLOAD_URL,
            ['project_id' => 42, 'token' => 'abc/def?evil=1&x=2'],
        );

        // The token must not introduce unescaped &, ?, /, = into the URL.
        self::assertStringContainsString('token=abc%2Fdef%3Fevil%3D1%26x%3D2', $url);
        self::assertStringNotContainsString('token=abc/def?evil=', $url);
    }
}
