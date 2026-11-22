<?php

declare(strict_types=1);

namespace Stackin\Errors;

/**
 * A 422 with detail.code ITEM_KIND_MISMATCH: a service item on an NF-e or a
 * product item on an NFS-e. $itemId names it and $index the line.
 */
final class ItemKindMismatchError extends ApiError
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
