<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * gAjusteBCLocImoveis: `type` is "01" to "04" or "99", and `description` goes
 * with "99".
 */
final class RealEstateBaseAdjustment
{
    public readonly string $type;

    public readonly float $amount;

    public readonly ?string $description;

    public function __construct(
        string $type,
        float $amount,
        ?string $description = null,
    ) {
        $this->type = Check::requiredChoice(
            $type,
            'ibs_cbs.real_estate.units.base_adjustments.type',
            ['01', '02', '03', '04', '99'],
        );
        $this->amount = Check::requiredNumber(
            $amount,
            'ibs_cbs.real_estate.units.base_adjustments.amount',
            0,
            999999999999999.0,
        );
        $this->description = Check::text(
            $description,
            'ibs_cbs.real_estate.units.base_adjustments.description',
            1,
            150,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'type' => $this->type,
            'amount' => $this->amount,
            'description' => $this->description,
        ]);
    }
}
