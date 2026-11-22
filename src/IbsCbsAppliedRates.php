<?php

declare(strict_types=1);

namespace Stackin;

/**
 * The rates a simulated item was computed with, as decimal strings.
 */
final class IbsCbsAppliedRates
{
    public function __construct(
        public readonly string $rateState,
        public readonly string $rateCity,
        public readonly string $rateFederal,
    ) {
    }

    /**
     * @param array<array-key, mixed>|null $data
     */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        return new self(
            rateState: (string) ($data['rate_state'] ?? '0'),
            rateCity: (string) ($data['rate_city'] ?? '0'),
            rateFederal: (string) ($data['rate_federal'] ?? '0'),
        );
    }
}
