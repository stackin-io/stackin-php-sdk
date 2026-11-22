<?php

declare(strict_types=1);

namespace Stackin\Errors;

/**
 * A 404 with detail.code ITEM_NOT_FOUND: no item of this company with that
 * id (another company's is the same answer). $itemId echoes it.
 */
final class ItemNotFoundError extends ApiError
{
    public function __construct(
        int $statusCode,
        string $detail,
        public readonly string $itemId = '',
    ) {
        parent::__construct($statusCode, $detail);
    }
}
