<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Studio;

use RuntimeException;
use Throwable;

/**
 * Thrown by {@see ScratchApi} for a non-2xx response or a transport failure.
 *
 * `$statusCode` is 0 for transport-level failures (DNS, connection reset,
 * timeout) where no HTTP response was received. `$retryAfterSeconds` is parsed
 * from the `Retry-After` header when present (delta-seconds form only;
 * HTTP-date form is treated as null).
 */
final class ScratchApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $url,
        public readonly string $bodySnippet = '',
        public readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
