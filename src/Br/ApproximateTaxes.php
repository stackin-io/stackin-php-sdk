<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * The approximate tax burden of Lei 12.741 (DPS totTrib). Send exactly one
 * shape: `simplesPercent`; the federal, state and municipal percentages; or
 * the federal, state and municipal amounts. A left-out member of the chosen
 * group is written as 0 by the API.
 */
final class ApproximateTaxes
{
    public readonly ?string $simplesPercent;

    public readonly ?string $federalPercent;

    public readonly ?string $statePercent;

    public readonly ?string $municipalPercent;

    public readonly ?string $federalAmount;

    public readonly ?string $stateAmount;

    public readonly ?string $municipalAmount;

    public function __construct(
        float|string|null $simplesPercent = null,
        float|string|null $federalPercent = null,
        float|string|null $statePercent = null,
        float|string|null $municipalPercent = null,
        float|string|null $federalAmount = null,
        float|string|null $stateAmount = null,
        float|string|null $municipalAmount = null,
    ) {
        $this->simplesPercent = Check::decimal(
            $simplesPercent,
            'nfse.approximate_taxes.simples_percent',
            0,
            false,
            99.99,
            2,
        );
        $this->federalPercent = Check::decimal(
            $federalPercent,
            'nfse.approximate_taxes.federal_percent',
            0,
            false,
            100,
            2,
        );
        $this->statePercent = Check::decimal(
            $statePercent,
            'nfse.approximate_taxes.state_percent',
            0,
            false,
            100,
            2,
        );
        $this->municipalPercent = Check::decimal(
            $municipalPercent,
            'nfse.approximate_taxes.municipal_percent',
            0,
            false,
            100,
            2,
        );
        $this->federalAmount = Check::decimal(
            $federalAmount,
            'nfse.approximate_taxes.federal_amount',
            0,
            false,
            1000000000000000.0,
            2,
        );
        $this->stateAmount = Check::decimal(
            $stateAmount,
            'nfse.approximate_taxes.state_amount',
            0,
            false,
            1000000000000000.0,
            2,
        );
        $this->municipalAmount = Check::decimal(
            $municipalAmount,
            'nfse.approximate_taxes.municipal_amount',
            0,
            false,
            1000000000000000.0,
            2,
        );

        $shapes = Check::count(
            $this->simplesPercent !== null,
            $this->federalPercent !== null
                || $this->statePercent !== null
                || $this->municipalPercent !== null,
            $this->federalAmount !== null
                || $this->stateAmount !== null
                || $this->municipalAmount !== null,
        );
        if ($shapes === 0) {
            Check::fail(
                'nfse.approximate_taxes',
                'needs simples_percent, the federal/state/municipal percentages or the ' .
                'federal/state/municipal amounts',
            );
        }
        if ($shapes > 1) {
            Check::fail(
                'nfse.approximate_taxes',
                'takes one shape: simples_percent, the percentages or the amounts',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'simples_percent' => $this->simplesPercent,
            'federal_percent' => $this->federalPercent,
            'state_percent' => $this->statePercent,
            'municipal_percent' => $this->municipalPercent,
            'federal_amount' => $this->federalAmount,
            'state_amount' => $this->stateAmount,
            'municipal_amount' => $this->municipalAmount,
        ]);
    }
}
