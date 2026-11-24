<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * gDif: the deferred share of each tax, in percent.
 */
final class IbsCbsDeferral
{
    public readonly float $statePercent;

    public readonly float $cityPercent;

    public readonly float $cbsPercent;

    public function __construct(
        float $statePercent,
        float $cityPercent,
        float $cbsPercent,
    ) {
        $this->statePercent = Check::requiredNumber(
            $statePercent,
            'ibs_cbs.deferral.state_percent',
            0,
            100,
        );
        $this->cityPercent = Check::requiredNumber(
            $cityPercent,
            'ibs_cbs.deferral.city_percent',
            0,
            100,
        );
        $this->cbsPercent = Check::requiredNumber(
            $cbsPercent,
            'ibs_cbs.deferral.cbs_percent',
            0,
            100,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'state_percent' => $this->statePercent,
            'city_percent' => $this->cityPercent,
            'cbs_percent' => $this->cbsPercent,
        ]);
    }
}
