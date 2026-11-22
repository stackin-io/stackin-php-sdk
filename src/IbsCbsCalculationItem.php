<?php

declare(strict_types=1);

namespace Stackin;

use Stackin\Errors\InvoiceError;

/**
 * One item of an IBS/CBS simulation.
 *
 * `cst` is the 3-digit CST and `classification` the 6-digit cClassTrib.
 * Give `amount`, `base` or both, each above zero. The rates and the two
 * reductions are percentages from 0 to 100; every optional field is left
 * off the request when null.
 */
final class IbsCbsCalculationItem
{
    private const KEYS = [
        'cst',
        'classification',
        'amount',
        'base',
        'rate_state',
        'rate_city',
        'rate_federal',
        'reduction_ibs',
        'reduction_cbs',
    ];

    public function __construct(
        public readonly string $cst,
        public readonly string $classification,
        public readonly ?float $amount = null,
        public readonly ?float $base = null,
        public readonly ?float $rateState = null,
        public readonly ?float $rateCity = null,
        public readonly ?float $rateFederal = null,
        public readonly ?float $reductionIbs = null,
        public readonly ?float $reductionCbs = null,
    ) {
        if (preg_match('/^\d{3}$/', $cst) !== 1) {
            throw new InvoiceError('cst must be 3 digits');
        }
        if (preg_match('/^\d{6}$/', $classification) !== 1) {
            throw new InvoiceError('classification must be 6 digits');
        }
        if ($amount === null && $base === null) {
            throw new InvoiceError('an item needs an amount or a base');
        }
        foreach (['amount' => $amount, 'base' => $base] as $name => $value) {
            if ($value !== null && $value <= 0) {
                throw new InvoiceError("{$name} must be greater than zero");
            }
        }
        $percents = [
            'rate_state' => $rateState,
            'rate_city' => $rateCity,
            'rate_federal' => $rateFederal,
            'reduction_ibs' => $reductionIbs,
            'reduction_cbs' => $reductionCbs,
        ];
        foreach ($percents as $name => $value) {
            self::checkPercent($name, $value);
        }
    }

    /**
     * @param array<string, mixed> $data snake_case keys, as sent to the API
     */
    public static function fromArray(array $data): self
    {
        $unknown = array_diff(array_keys($data), self::KEYS);
        if ($unknown !== []) {
            throw new InvoiceError('unknown item keys: ' . implode(', ', $unknown));
        }

        return new self(
            cst: (string) ($data['cst'] ?? ''),
            classification: (string) ($data['classification'] ?? ''),
            amount: self::number($data['amount'] ?? null, 'amount'),
            base: self::number($data['base'] ?? null, 'base'),
            rateState: self::number($data['rate_state'] ?? null, 'rate_state'),
            rateCity: self::number($data['rate_city'] ?? null, 'rate_city'),
            rateFederal: self::number($data['rate_federal'] ?? null, 'rate_federal'),
            reductionIbs: self::number($data['reduction_ibs'] ?? null, 'reduction_ibs'),
            reductionCbs: self::number($data['reduction_cbs'] ?? null, 'reduction_cbs'),
        );
    }

    /**
     * @return array<string, string|float>
     */
    public function toArray(): array
    {
        return array_filter(
            [
                'cst' => $this->cst,
                'classification' => $this->classification,
                'amount' => $this->amount,
                'base' => $this->base,
                'rate_state' => $this->rateState,
                'rate_city' => $this->rateCity,
                'rate_federal' => $this->rateFederal,
                'reduction_ibs' => $this->reductionIbs,
                'reduction_cbs' => $this->reductionCbs,
            ],
            static fn (string|float|null $value): bool => $value !== null,
        );
    }

    /**
     * @internal Shared with IbsCbsRates.
     */
    public static function checkPercent(string $name, ?float $value): void
    {
        if ($value !== null && ($value < 0 || $value > 100)) {
            throw new InvoiceError("{$name} must be between 0 and 100");
        }
    }

    /**
     * @internal Shared with IbsCbsRates.
     */
    public static function number(mixed $value, string $name): ?float
    {
        if ($value === null) {
            return null;
        }
        if (!is_int($value) && !is_float($value)) {
            throw new InvoiceError("{$name} must be a number");
        }

        return (float) $value;
    }
}
