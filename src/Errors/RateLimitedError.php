<?php

declare(strict_types=1);

namespace Stackin\Errors;

use DateTimeImmutable;

/**
 * A 429: the call came too soon. $retryAfter is the Retry-After header
 * in seconds and $retryAt the moment the body names, when either was
 * sent. $errorCode is the body's code and says which limit, e.g.
 * DISTRIBUTION_COOLDOWN for the SEFAZ one-hour wait after a
 * received-invoice read found nothing more. It is not named $code
 * because Exception already owns that property.
 *
 * Retrying before $retryAt is refused the same way.
 */
final class RateLimitedError extends ApiError
{
    public function __construct(
        int $statusCode,
        string $detail,
        public readonly ?int $retryAfter = null,
        public readonly ?DateTimeImmutable $retryAt = null,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($statusCode, $detail);
    }
}
