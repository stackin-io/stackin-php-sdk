<?php

declare(strict_types=1);

namespace Stackin;

use Stackin\Errors\InvoiceError;

/**
 * Default IBS/CBS rates for a simulation, in percent from 0 to 100.
 *
 * An item's own rate wins. Every field is left off the request when null.
 */
final class IbsCbsRates
{
    public function __construct(
        public readonly ?float $rateState = null,
        public readonly ?float $rateCity = null,
        public readonly ?float $rateFederal = null,
    ) {
        IbsCbsCalculationItem::checkPercent('rate_state', $rateState);
        IbsCbsCalculationItem::checkPercent('rate_city', $rateCity);
        IbsCbsCalculationItem::checkPercent('rate_federal', $rateFederal);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $unknown = array_diff(array_keys($data), ['rate_state', 'rate_city', 'rate_federal']);
        if ($unknown !== []) {
            throw new InvoiceError('unknown rates keys: ' . implode(', ', $unknown));
        }

        return new self(
            IbsCbsCalculationItem::number($data['rate_state'] ?? null, 'rate_state'),
            IbsCbsCalculationItem::number($data['rate_city'] ?? null, 'rate_city'),
            IbsCbsCalculationItem::number($data['rate_federal'] ?? null, 'rate_federal'),
        );
    }

    /**
     * @return array<string, float>
     */
    public function toArray(): array
    {
        return array_filter(
            [
                'rate_state' => $this->rateState,
                'rate_city' => $this->rateCity,
                'rate_federal' => $this->rateFederal,
            ],
            static fn (?float $value): bool => $value !== null,
        );
    }
}
