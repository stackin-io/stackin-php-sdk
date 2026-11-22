<?php

declare(strict_types=1);

namespace Stackin\Errors;

/**
 * A 422 with detail.code ITEM_TAX_REGIME_MISMATCH: the item's CST/CSOSN does
 * not fit the company's tax regime (a CSOSN is for Simples Nacional, a CST
 * for the rest), or is not allowed on an export CFOP. Send the line's own
 * tax, or fix the item. $itemId names the item and $index the line.
 */
final class ItemTaxRegimeMismatchError extends ApiError
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
