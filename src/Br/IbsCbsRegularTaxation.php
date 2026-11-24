<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * gTribRegular: the `cst` (3 digits) and `classification` (6 digits) the
 * operation would have under regular taxation.
 */
final class IbsCbsRegularTaxation
{
    public readonly string $cst;

    public readonly string $classification;

    public function __construct(
        string $cst,
        string $classification,
    ) {
        $this->cst = Check::requiredPattern(
            $cst,
            'ibs_cbs.regular_taxation.cst',
            '/^\d{3}$/',
            '3 digits',
        );
        $this->classification = Check::requiredPattern(
            $classification,
            'ibs_cbs.regular_taxation.classification',
            '/^\d{6}$/',
            '6 digits',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'cst' => $this->cst,
            'classification' => $this->classification,
        ]);
    }
}
