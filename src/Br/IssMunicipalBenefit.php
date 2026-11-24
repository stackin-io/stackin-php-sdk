<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * The municipality's registered benefit (BM): `id` is the 14-digit nBM, and
 * at most one of `reductionAmount` and `reductionPercent` goes with it.
 */
final class IssMunicipalBenefit
{
    public readonly string $id;

    public readonly ?string $reductionAmount;

    public readonly ?string $reductionPercent;

    public function __construct(
        string $id,
        float|string|null $reductionAmount = null,
        float|string|null $reductionPercent = null,
    ) {
        $this->id = Check::requiredPattern(
            $id,
            'nfse.iss.municipal_benefit.id',
            '/^[0-9]{14}$/',
            '14 digits',
        );
        $this->reductionAmount = Check::decimal(
            $reductionAmount,
            'nfse.iss.municipal_benefit.reduction_amount',
            0,
            false,
            1000000000000000.0,
            2,
        );
        $this->reductionPercent = Check::decimal(
            $reductionPercent,
            'nfse.iss.municipal_benefit.reduction_percent',
            0,
            true,
            100,
            2,
        );

        if ($this->reductionAmount !== null && $this->reductionPercent !== null) {
            Check::fail(
                'nfse.iss.municipal_benefit',
                'takes reduction_amount or reduction_percent, not both',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'id' => $this->id,
            'reduction_amount' => $this->reductionAmount,
            'reduction_percent' => $this->reductionPercent,
        ]);
    }
}
