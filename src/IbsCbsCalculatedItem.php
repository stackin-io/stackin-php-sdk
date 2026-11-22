<?php

declare(strict_types=1);

namespace Stackin;

/**
 * One simulated item, in the order it was sent.
 *
 * `ratesSource` is "caller" or "official_2026" when the API applied
 * rates and null otherwise; the rates, reductions and deferral are
 * decimal strings or null.
 */
final class IbsCbsCalculatedItem
{
    public function __construct(
        public readonly string $cst,
        public readonly string $classification,
        public readonly IbsCbsIndicators $indicators,
        public readonly bool $hasGroup,
        public readonly ?IbsCbsAppliedRates $rates,
        public readonly ?string $ratesSource,
        public readonly ?string $reductionIbs,
        public readonly ?string $reductionCbs,
        public readonly ?IbsCbsAppliedRates $effectiveRates,
        public readonly ?string $deferral,
        public readonly IbsCbsAmounts $amounts,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $indicators = $data['indicators'] ?? [];
        $rates = $data['rates'] ?? null;
        $effective = $data['effective_rates'] ?? null;
        $amounts = $data['amounts'] ?? [];

        return new self(
            cst: (string) ($data['cst'] ?? ''),
            classification: (string) ($data['classification'] ?? ''),
            indicators: IbsCbsIndicators::fromArray(is_array($indicators) ? $indicators : []),
            hasGroup: (bool) ($data['has_group'] ?? false),
            rates: IbsCbsAppliedRates::fromArray(is_array($rates) ? $rates : null),
            ratesSource: isset($data['rates_source']) ? (string) $data['rates_source'] : null,
            reductionIbs: isset($data['reduction_ibs']) ? (string) $data['reduction_ibs'] : null,
            reductionCbs: isset($data['reduction_cbs']) ? (string) $data['reduction_cbs'] : null,
            effectiveRates: IbsCbsAppliedRates::fromArray(is_array($effective) ? $effective : null),
            deferral: isset($data['deferral']) ? (string) $data['deferral'] : null,
            amounts: IbsCbsAmounts::fromArray(is_array($amounts) ? $amounts : []),
        );
    }
}
