<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * One volume of an NF-e's transport: packages carried and their weight in
 * kg, at most 3 decimal places.
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
        Decimals::optional($netWeight, 'volume netWeight', 3);
        Decimals::optional($grossWeight, 'volume grossWeight', 3);
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
                'net_weight' => Decimals::optional($this->netWeight, 'volume netWeight', 3),
                'gross_weight' => Decimals::optional(
                    $this->grossWeight,
                    'volume grossWeight',
                    3,
                ),
            ],
            static fn (mixed $value): bool => $value !== null,
        );
    }
}
