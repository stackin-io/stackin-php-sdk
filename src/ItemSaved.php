<?php

declare(strict_types=1);

namespace Stackin;

/**
 * The answer of Items::upsert(): the item as the API returns it (an
 * array, like Clients), and whether the call created it (201) or updated
 * the company's existing one (200).
 */
final class ItemSaved
{
    /**
     * @param array<array-key, mixed> $item
     */
    public function __construct(
        public readonly array $item,
        public readonly bool $created,
    ) {
    }
}
