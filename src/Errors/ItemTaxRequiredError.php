<?php

declare(strict_types=1);

namespace Stackin\Errors;

/**
 * A 422 with detail.code ITEM_TAX_REQUIRED: the item's tax situation needs a
 * base and a rate the catalog does not keep, so the line must send its
 * own tax. $itemId names the item and $index the line.
 */
final class ItemTaxRequiredError extends ApiError
{
    public function __construct(
        int $statusCode,
        string $detail,
        public readonly string $itemId = '',
        public readonly ?int $index = null,
    ) {
        parent::__construct($statusCode, $detail);
    }
}
