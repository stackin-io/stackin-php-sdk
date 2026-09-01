<?php

declare(strict_types=1);

namespace Stackin\Errors;

use Exception;

/**
 * The API responded with a non-2xx status. A 401 specifically means
 * the api_key is missing, wrong, or was rotated.
 */
final class ApiError extends Exception
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $detail,
    ) {
        parent::__construct(sprintf('[%d] %s', $statusCode, $detail));
    }
}
