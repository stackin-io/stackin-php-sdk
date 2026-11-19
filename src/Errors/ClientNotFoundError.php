<?php

declare(strict_types=1);

namespace Stackin\Errors;

/**
 * A 404 with detail.code CLIENT_NOT_FOUND: no saved client with this
 * id exists for the company. $clientId echoes the id the API was
 * asked for, when it named one.
 */
final class ClientNotFoundError extends ApiError
{
    public function __construct(
        int $statusCode,
        string $detail,
        public readonly string $clientId = '',
    ) {
        parent::__construct($statusCode, $detail);
    }
}
