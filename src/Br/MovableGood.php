<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * One bensMoveis entry: `ncm` (8 digits), `description` and a `quantity` from
 * 1 to 999.
 */
final class MovableGood
{
    public readonly string $ncm;

    public readonly string $description;

    public readonly int $quantity;

    public function __construct(
        string $ncm,
        string $description,
        int $quantity,
    ) {
        $this->ncm = Check::requiredPattern(
            $ncm,
            'ibs_cbs.movable_goods.ncm',
            '/^\d{8}$/',
            '8 digits',
        );
        $this->description = Check::required(
            $description,
            'ibs_cbs.movable_goods.description',
            1,
            150,
        );
        $this->quantity = Check::requiredIntegerRange(
            $quantity,
            'ibs_cbs.movable_goods.quantity',
            1,
            999,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'ncm' => $this->ncm,
            'description' => $this->description,
            'quantity' => $this->quantity,
        ]);
    }
}
