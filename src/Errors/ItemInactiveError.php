<?php

declare(strict_types=1);

namespace Stackin\Errors;

/**
 * A 422 with detail.code ITEM_INACTIVE: issue() with the itemId of a
 * deactivated item. $itemId names it; Items::reactivate() brings it back.
 */
final class ItemInactiveError extends ApiError
{
    public function __construct(
        int $statusCode,
        string $detail,
        public readonly string $itemId = '',
    ) {
        parent::__construct($statusCode, $detail);
    }
}
