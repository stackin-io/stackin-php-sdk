<?php

declare(strict_types=1);

namespace Stackin\Errors;

/**
 * A 403 with detail.code INSUFFICIENT_PERMISSION: the credential
 * reaches the company but was not granted this operation there.
 * $description reads "needs X, has Y, company=<id>".
 *
 * Never retried: the grant has to change first.
 */
final class InsufficientPermissionError extends ApiError
{
    public function __construct(
        int $statusCode,
        string $detail,
        public readonly string $description,
    ) {
        parent::__construct($statusCode, $detail);
    }
}
