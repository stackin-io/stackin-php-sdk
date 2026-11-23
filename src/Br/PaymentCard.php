<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * The electronic payment's processor and authorization. `integration` is
 * tpIntegra: "1" integrated with the seller's system, "2" not integrated.
 * `brand` is the two-digit tBand code.
 */
final class PaymentCard
{
    public function __construct(
        public readonly string $integration,
        public readonly ?string $taxId = null,
        public readonly ?string $brand = null,
        public readonly ?string $authorization = null,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter(
            [
                'integration' => $this->integration,
                'tax_id' => $this->taxId,
                'brand' => $this->brand,
                'authorization' => $this->authorization,
            ],
            static fn (?string $value): bool => $value !== null,
        );
    }
}
