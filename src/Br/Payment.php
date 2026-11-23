<?php

declare(strict_types=1);

namespace Stackin\Br;

use Stackin\Errors\InvoiceError;

/**
 * One payment of an NF-e (detPag). `method` is the two-digit tPag code,
 * such as "01", "03", "17", "90" or "99"; "99" needs `description` naming the method. `amount` is in BRL, 0 for "90".
 */
final class Payment
{
    public function __construct(
        public readonly string $method,
        public readonly float|string $amount,
        public readonly ?string $description = null,
        public readonly ?PaymentCard $card = null,
    ) {
        if (preg_match('/^\d{2}$/', $method) !== 1) {
            throw new InvoiceError("payment method must be a two-digit code, got '{$method}'");
        }
        if ($method === '99' && ($description === null || trim($description) === '')) {
            throw new InvoiceError("payment method '99' requires a description");
        }
        Decimals::format($amount, 'payment amount', 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(
            [
                'method' => $this->method,
                'amount' => Decimals::format($this->amount, 'payment amount', 2),
                'description' => $this->description,
                'card' => $this->card?->toArray(),
            ],
            static fn (mixed $value): bool => $value !== null,
        );
    }
}
