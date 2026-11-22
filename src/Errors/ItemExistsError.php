<?php

declare(strict_types=1);

namespace Stackin\Errors;

/**
 * A 409 with detail.code ITEM_EXISTS: the company already has an item with
 * this code or external_reference (a deactivated item still holds its
 * code). $itemId names it; $field is `code` or `external_reference`.
 */
final class ItemExistsError extends ApiError
{
    public function __construct(
        int $statusCode,
        string $detail,
        public readonly string $itemId = '',
        public readonly ?string $field = null,
    ) {
        parent::__construct($statusCode, $detail);
    }
}
