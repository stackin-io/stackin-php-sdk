<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * The ISSQN of the service (DPS tribMun). `taxation` is "taxable" (the API's
 * default), "immune", "export" or "not_levied"; `retention` is "none",
 * "tomador" or "intermediary".
 */
final class IssDetails
{
    public readonly ?string $taxation;

    public readonly ?int $immunityType;

    public readonly ?string $resultCountryCode;

    public readonly ?IssSuspension $suspension;

    public readonly ?string $rate;

    public readonly ?string $retention;

    public readonly ?IssMunicipalBenefit $municipalBenefit;

    public function __construct(
        ?string $taxation = null,
        ?int $immunityType = null,
        ?string $resultCountryCode = null,
        ?IssSuspension $suspension = null,
        float|string|null $rate = null,
        ?string $retention = null,
        ?IssMunicipalBenefit $municipalBenefit = null,
    ) {
        $this->taxation = Check::choice(
            $taxation,
            'nfse.iss.taxation',
            ['taxable', 'immune', 'export', 'not_levied'],
        );
        $this->immunityType = Check::integer(
            $immunityType,
            'nfse.iss.immunity_type',
            [1, 2, 3, 4, 5],
        );
        $this->resultCountryCode = Check::pattern(
            $resultCountryCode,
            'nfse.iss.result_country_code',
            '/^[A-Z]{2}$/',
            'an ISO 3166 alpha-2 code',
        );
        $this->suspension = $suspension;
        $this->rate = Check::decimal($rate, 'nfse.iss.rate', 0, true, 5, 2);
        $this->retention = Check::choice(
            $retention,
            'nfse.iss.retention',
            ['none', 'tomador', 'intermediary'],
        );
        $this->municipalBenefit = $municipalBenefit;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'taxation' => $this->taxation,
            'immunity_type' => $this->immunityType,
            'result_country_code' => $this->resultCountryCode,
            'suspension' => $this->suspension?->toArray(),
            'rate' => $this->rate,
            'retention' => $this->retention,
            'municipal_benefit' => $this->municipalBenefit?->toArray(),
        ]);
    }
}
