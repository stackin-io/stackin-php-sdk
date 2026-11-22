<?php

declare(strict_types=1);

namespace Stackin;

/**
 * The answer of Items::upsert(): the item, and whether the call created
 * it (201) or updated the company's existing one (200).
 */
final class ItemSaved
{
    public function __construct(
        public readonly Item $item,
        public readonly bool $created,
    ) {
    }
}
