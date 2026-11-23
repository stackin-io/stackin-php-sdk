<?php

declare(strict_types=1);

namespace Stackin\Br;

use Stackin\Errors\InvoiceError;

/**
 * One volume of an NF-e's transport: packages carried and their weight in
 * kg.
 */
final class TransportVolume
{
    public function __construct(
        public readonly ?int $quantity = null,
        public readonly ?string $species = null,
        public readonly ?string $brand = null,
        public readonly ?string $numbering = null,
        public readonly float|string|null $netWeight = null,
        public readonly float|string|null $grossWeight = null,
    ) {
        if ($quantity !== null && $quantity < 0) {
            throw new InvoiceError('volume quantity cannot be negative');
        }
        Decimals::optional($netWeight, 'volume netWeight');
        Decimals::optional($grossWeight, 'volume grossWeight');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(
            [
                'quantity' => $this->quantity,
                'species' => $this->species,
                'brand' => $this->brand,
                'numbering' => $this->numbering,
                'net_weight' => Decimals::optional($this->netWeight, 'volume netWeight'),
                'gross_weight' => Decimals::optional(
                    $this->grossWeight,
                    'volume grossWeight',
                ),
            ],
            static fn (mixed $value): bool => $value !== null,
        );
    }
}
