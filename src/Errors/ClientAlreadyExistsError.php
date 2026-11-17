<?php

declare(strict_types=1);

namespace Stackin\Errors;

/**
 * A 409 with detail.code CLIENT_EXISTS: the company already has a
 * client with this tax_id or foreign_id. $clientId names it, to read or
 * reactivate instead of searching for it.
 */
final class ClientAlreadyExistsError extends ApiError
{
    public function __construct(
        int $statusCode,
        string $detail,
        public readonly string $clientId,
    ) {
        parent::__construct($statusCode, $detail);
    }
}
