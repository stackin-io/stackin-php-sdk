<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * The PIS and COFINS of the service. `cst` is required; `withholdingType` is
 * tpRetPisCofins, 0 to 9.
 */
final class PisCofins
{
    public readonly string $cst;

    public readonly ?string $base;

    public readonly ?string $pisRate;

    public readonly ?string $cofinsRate;

    public readonly ?string $pisAmount;

    public readonly ?string $cofinsAmount;

    public readonly ?int $withholdingType;

    public function __construct(
        string $cst,
        float|string|null $base = null,
        float|string|null $pisRate = null,
        float|string|null $cofinsRate = null,
        float|string|null $pisAmount = null,
        float|string|null $cofinsAmount = null,
        ?int $withholdingType = null,
    ) {
        $this->cst = Check::requiredChoice(
            $cst,
            'nfse.federal.pis_cofins.cst',
            Check::LIST_PIS_COFINS_CST,
        );
        $this->base = Check::decimal(
            $base,
            'nfse.federal.pis_cofins.base',
            0,
            false,
            1000000000000000.0,
            2,
        );
        $this->pisRate = Check::decimal(
            $pisRate,
            'nfse.federal.pis_cofins.pis_rate',
            0,
            false,
            99.99,
            2,
        );
        $this->cofinsRate = Check::decimal(
            $cofinsRate,
            'nfse.federal.pis_cofins.cofins_rate',
            0,
            false,
            99.99,
            2,
        );
        $this->pisAmount = Check::decimal(
            $pisAmount,
            'nfse.federal.pis_cofins.pis_amount',
            0,
            false,
            1000000000000000.0,
            2,
        );
        $this->cofinsAmount = Check::decimal(
            $cofinsAmount,
            'nfse.federal.pis_cofins.cofins_amount',
            0,
            false,
            1000000000000000.0,
            2,
        );
        $this->withholdingType = Check::integer(
            $withholdingType,
            'nfse.federal.pis_cofins.withholding_type',
            [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'cst' => $this->cst,
            'base' => $this->base,
            'pis_rate' => $this->pisRate,
            'cofins_rate' => $this->cofinsRate,
            'pis_amount' => $this->pisAmount,
            'cofins_amount' => $this->cofinsAmount,
            'withholding_type' => $this->withholdingType,
        ]);
    }
}
