<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Studio;

use JsonException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin client for the public Scratch API.
 *
 * Every request runs with explicit timeouts and (by default) automatic retries
 * on transient failures (429 + 5xx, honouring `Retry-After`). Failures surface
 * as {@see ScratchApiException} carrying the status code, URL, and a short
 * snippet of the response body so callers can branch on them programmatically.
 */
class ScratchApi
{
    public const STUDIO_PROJECTS_URL = 'https://api.scratch.mit.edu/studios/{studio_id}/projects';
    public const PROJECT_META_URL = 'https://api.scratch.mit.edu/projects/{project_id}';
    public const PROJECT_DOWNLOAD_URL = 'https://projects.scratch.mit.edu/{project_id}?token={token}';
    public const ASSET_URL = 'https://assets.scratch.mit.edu/internalapi/asset/{md5ext}/get/';

    private const string USER_AGENT = 'sb3-toolkit/1.0 (+https://scratch.mit.edu)';

    /** Per-chunk wait (seconds) before the connection is considered idle. */
    private const float DEFAULT_TIMEOUT = 10.0;

    /** Hard cap on a single request's wall-clock time, including retries. */
    private const float DEFAULT_MAX_DURATION = 60.0;

    /** How many times to re-issue a request after a retryable failure. */
    private const int DEFAULT_MAX_RETRIES = 3;

    /** How much of a failed response body to surface in error messages. */
    private const int ERROR_BODY_SNIPPET_BYTES = 200;

    private readonly HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null, bool $autoRetry = true)
    {
        $base = $client ?? HttpClient::create([
            'timeout' => self::DEFAULT_TIMEOUT,
            'max_duration' => self::DEFAULT_MAX_DURATION,
            'headers' => ['User-Agent' => self::USER_AGENT],
        ]);

        $this->client = $autoRetry
            ? new RetryableHttpClient($base, new GenericRetryStrategy(), self::DEFAULT_MAX_RETRIES)
            : $base;
    }

    /**
     * Expose the underlying HTTP client (used by callers that want to dispatch
     * many requests concurrently via {@see HttpClientInterface::stream()}).
     */
    public function client(): HttpClientInterface
    {
        return $this->client;
    }

    /**
     * Substitute `{key}` placeholders in a URL template with rawurlencoded
     * parameter values, so opaque tokens or values containing reserved
     * characters cannot corrupt the URL.
     *
     * @param array<string, string|int> $params
     */
    public static function formatUrl(string $template, array $params): string
    {
        $out = $template;
        foreach ($params as $key => $value) {
            $out = self::replaceFirst($out, '{' . $key . '}', rawurlencode((string) $value));
        }

        return $out;
    }

    /**
     * Fetch a URL and return the response body as a binary string.
     *
     * @throws ScratchApiException on a non-2xx response or transport failure
     */
    public function requestBytes(string $url): string
    {
        try {
            $response = $this->client->request('GET', $url, [
                'headers' => ['User-Agent' => self::USER_AGENT],
            ]);
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw new ScratchApiException(
                    \sprintf('Request failed (%d) for %s', $status, $url),
                    $status,
                    $url,
                    $this->truncate($response->getContent(false)),
                    $this->parseRetryAfter($response->getHeaders(false)),
                );
            }

            return $response->getContent();
        } catch (TransportExceptionInterface $error) {
            throw new ScratchApiException(
                \sprintf('Transport error for %s: %s', $url, $error->getMessage()),
                0,
                $url,
                previous: $error,
            );
        }
    }

    /**
     * Stream a URL straight to disk, never holding the full body in memory.
     *
     * The download lands at `$destPath.tmp` first, then is atomically renamed
     * so a killed mid-download process never leaves a half-written file at
     * the target path. Use this for genuinely large payloads — for typical
     * `.sb3`-sized bodies {@see requestBytes()} is simpler and just as fast.
     *
     * @throws ScratchApiException on a non-2xx response, transport failure, or
     *                             disk write failure
     */
    public function streamToFile(string $url, string $destPath): void
    {
        $tmpPath = $destPath . '.tmp';
        try {
            $response = $this->client->request('GET', $url, [
                'headers' => ['User-Agent' => self::USER_AGENT],
            ]);
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw new ScratchApiException(
                    \sprintf('Request failed (%d) for %s', $status, $url),
                    $status,
                    $url,
                    $this->truncate($response->getContent(false)),
                    $this->parseRetryAfter($response->getHeaders(false)),
                );
            }

            $handle = fopen($tmpPath, 'wb');
            if ($handle === false) {
                throw new ScratchApiException(
                    \sprintf('Failed to open temp file for streaming: %s', $tmpPath),
                    0,
                    $url,
                );
            }

            try {
                foreach ($this->client->stream($response) as $chunk) {
                    if (fwrite($handle, $chunk->getContent()) === false) {
                        throw new ScratchApiException(
                            \sprintf('Failed to write chunk to %s', $tmpPath),
                            0,
                            $url,
                        );
                    }
                }
            } finally {
                fclose($handle);
            }
        } catch (TransportExceptionInterface $error) {
            @unlink($tmpPath);

            throw new ScratchApiException(
                \sprintf('Transport error for %s: %s', $url, $error->getMessage()),
                0,
                $url,
                previous: $error,
            );
        } catch (ScratchApiException $error) {
            @unlink($tmpPath);

            throw $error;
        }

        if (!rename($tmpPath, $destPath)) {
            @unlink($tmpPath);

            throw new ScratchApiException(
                \sprintf('Failed to move temp file into place: %s', $destPath),
                0,
                $url,
            );
        }
    }

    /**
     * Fetch many URLs in parallel and return their bodies keyed by URL.
     *
     * Symfony's HttpClient multiplexes concurrent requests under the hood
     * (curl multi by default), so this is dramatically faster than calling
     * {@see requestBytes()} in a loop for I/O-bound batches like project
     * assets. All requests are dispatched up front; failures are aggregated
     * so a single 404 does not lose every other body in the batch.
     *
     * @param list<string> $urls
     *
     * @return array<string, string> body keyed by URL (only successful entries)
     *
     * @throws ScratchApiException if any request failed; the exception names
     *                             the failing URLs and carries the first error
     */
    public function requestBytesBatch(array $urls): array
    {
        if ($urls === []) {
            return [];
        }

        $responses = [];
        foreach (array_values(array_unique($urls)) as $url) {
            $responses[$url] = $this->client->request('GET', $url, [
                'headers' => ['User-Agent' => self::USER_AGENT],
            ]);
        }

        $bodies = [];
        $failures = [];
        foreach ($responses as $url => $response) {
            try {
                $status = $response->getStatusCode();
                if ($status < 200 || $status >= 300) {
                    $failures[$url] = new ScratchApiException(
                        \sprintf('Request failed (%d) for %s', $status, $url),
                        $status,
                        $url,
                        $this->truncate($response->getContent(false)),
                        $this->parseRetryAfter($response->getHeaders(false)),
                    );

                    continue;
                }
                $bodies[$url] = $response->getContent();
            } catch (TransportExceptionInterface $error) {
                $failures[$url] = new ScratchApiException(
                    \sprintf('Transport error for %s: %s', $url, $error->getMessage()),
                    0,
                    $url,
                    previous: $error,
                );
            }
        }

        if ($failures !== []) {
            throw $this->combineBatchFailures($failures);
        }

        return $bodies;
    }

    /**
     * Fetch a URL and parse the response body as JSON.
     *
     * @throws ScratchApiException on a non-2xx response, transport failure, or
     *                             malformed JSON body
     */
    public function requestJson(string $url): mixed
    {
        $body = $this->requestBytes($url);

        try {
            return json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new ScratchApiException(
                \sprintf('Failed to parse JSON from %s: %s', $url, $error->getMessage()),
                200,
                $url,
                $this->truncate($body),
                previous: $error,
            );
        }
    }

    /**
     * Sleep for the requested number of seconds.
     */
    public function delaySeconds(float $seconds): void
    {
        if ($seconds <= 0.0) {
            return;
        }
        usleep((int) round($seconds * 1_000_000));
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function parseRetryAfter(array $headers): ?int
    {
        $values = $headers['retry-after'] ?? [];
        foreach ($values as $value) {
            if (preg_match('/^\d+$/', $value) === 1) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, ScratchApiException> $failures
     */
    private function combineBatchFailures(array $failures): ScratchApiException
    {
        $first = reset($failures);
        \assert($first instanceof ScratchApiException);

        if (\count($failures) === 1) {
            return $first;
        }

        return new ScratchApiException(
            \sprintf(
                '%d concurrent requests failed. First: %s. URLs: %s',
                \count($failures),
                $first->getMessage(),
                implode(', ', array_keys($failures)),
            ),
            $first->statusCode,
            $first->url,
            $first->bodySnippet,
            $first->retryAfterSeconds,
            previous: $first,
        );
    }

    private function truncate(string $body): string
    {
        if (\strlen($body) <= self::ERROR_BODY_SNIPPET_BYTES) {
            return $body;
        }

        return substr($body, 0, self::ERROR_BODY_SNIPPET_BYTES) . '…';
    }

    private static function replaceFirst(string $haystack, string $needle, string $replacement): string
    {
        $position = strpos($haystack, $needle);
        if ($position === false) {
            return $haystack;
        }

        return substr_replace($haystack, $replacement, $position, \strlen($needle));
    }
}
