<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * The federal taxes of the service (DPS tribFed). Send at least one member.
 * The withheld amounts are in BRL and above 0.
 */
final class FederalTaxes
{
    public readonly ?PisCofins $pisCofins;

    public readonly ?string $socialSecurityWithheld;

    public readonly ?string $irrfWithheld;

    public readonly ?string $socialContributionsWithheld;

    public function __construct(
        ?PisCofins $pisCofins = null,
        float|string|null $socialSecurityWithheld = null,
        float|string|null $irrfWithheld = null,
        float|string|null $socialContributionsWithheld = null,
    ) {
        $this->pisCofins = $pisCofins;
        $this->socialSecurityWithheld = Check::decimal(
            $socialSecurityWithheld,
            'nfse.federal.social_security_withheld',
            0,
            true,
            1000000000000000.0,
            2,
        );
        $this->irrfWithheld = Check::decimal(
            $irrfWithheld,
            'nfse.federal.irrf_withheld',
            0,
            true,
            1000000000000000.0,
            2,
        );
        $this->socialContributionsWithheld = Check::decimal(
            $socialContributionsWithheld,
            'nfse.federal.social_contributions_withheld',
            0,
            true,
            1000000000000000.0,
            2,
        );

        if (
            $this->pisCofins === null
            && $this->socialSecurityWithheld === null
            && $this->irrfWithheld === null
            && $this->socialContributionsWithheld === null
        ) {
            Check::fail(
                'nfse.federal',
                'needs pis_cofins, social_security_withheld, irrf_withheld or ' .
                'social_contributions_withheld',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'pis_cofins' => $this->pisCofins?->toArray(),
            'social_security_withheld' => $this->socialSecurityWithheld,
            'irrf_withheld' => $this->irrfWithheld,
            'social_contributions_withheld' => $this->socialContributionsWithheld,
        ]);
    }
}
