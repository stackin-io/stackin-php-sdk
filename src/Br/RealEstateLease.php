<?php

declare(strict_types=1);

namespace Stackin\Br;

use DateTimeInterface;

/**
 * gLocacao, for a lease, onerous assignment or rental of real estate.
 * `coOwnershipPercent` is 100 with a single lessor.
 */
final class RealEstateLease
{
    public readonly float $coOwnershipPercent;

    public readonly float $totalAmount;

    public readonly ?float $unconditionalDiscount;

    public readonly ?float $conditionalDiscount;

    public readonly ?string $originalDueDate;

    public function __construct(
        float $coOwnershipPercent,
        float $totalAmount,
        ?float $unconditionalDiscount = null,
        ?float $conditionalDiscount = null,
        DateTimeInterface|string|null $originalDueDate = null,
    ) {
        $this->coOwnershipPercent = Check::requiredNumber(
            $coOwnershipPercent,
            'ibs_cbs.real_estate.lease.co_ownership_percent',
            0,
            100,
        );
        $this->totalAmount = Check::requiredNumber(
            $totalAmount,
            'ibs_cbs.real_estate.lease.total_amount',
            0,
            999999999999999.0,
        );
        $this->unconditionalDiscount = Check::number(
            $unconditionalDiscount,
            'ibs_cbs.real_estate.lease.unconditional_discount',
            0,
            999999999999999.0,
        );
        $this->conditionalDiscount = Check::number(
            $conditionalDiscount,
            'ibs_cbs.real_estate.lease.conditional_discount',
            0,
            999999999999999.0,
        );
        $this->originalDueDate = Check::day(
            $originalDueDate,
            'ibs_cbs.real_estate.lease.original_due_date',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'co_ownership_percent' => $this->coOwnershipPercent,
            'total_amount' => $this->totalAmount,
            'unconditional_discount' => $this->unconditionalDiscount,
            'conditional_discount' => $this->conditionalDiscount,
            'original_due_date' => $this->originalDueDate,
        ]);
    }
}
