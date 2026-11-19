<?php

declare(strict_types=1);

namespace Stackin;

use DateTimeImmutable;

/**
 * The account's consumption in the current billing period, from
 * GET /company/usage.
 *
 * Amounts are integer cents. overagePriceCents is null on a plan that
 * stops at its allowance instead of charging past it. Received
 * invoices: the first includedReceived a month are part of every plan,
 * then receivedPriceCents each is taken from creditBalance, unless
 * receivedFree says the account is exempt. Fields the API adds later
 * are ignored rather than refused.
 */
final class Usage
{
    public function __construct(
        public readonly string $planName,
        public readonly string $planSlug,
        public readonly int $includedInvoices,
        public readonly int $usedInvoices,
        public readonly ?int $overagePriceCents,
        public readonly DateTimeImmutable $currentPeriodStart,
        public readonly ?DateTimeImmutable $currentPeriodEnd,
        public readonly int $creditBalance,
        public readonly int $companiesOnAccount,
        public readonly int $includedReceived = 50,
        public readonly int $usedReceived = 0,
        public readonly int $receivedPriceCents = 5,
        public readonly bool $receivedFree = false,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $end = $data['current_period_end'] ?? null;

        return new self(
            planName: (string) ($data['plan_name'] ?? ''),
            planSlug: (string) ($data['plan_slug'] ?? ''),
            includedInvoices: (int) ($data['included_invoices'] ?? 0),
            usedInvoices: (int) ($data['used_invoices'] ?? 0),
            overagePriceCents: isset($data['overage_price_cents'])
                ? (int) $data['overage_price_cents']
                : null,
            currentPeriodStart: new DateTimeImmutable(
                (string) ($data['current_period_start'] ?? 'now')
            ),
            currentPeriodEnd: is_string($end) && $end !== '' ? new DateTimeImmutable($end) : null,
            creditBalance: (int) ($data['credit_balance'] ?? 0),
            companiesOnAccount: (int) ($data['companies_on_account'] ?? 0),
            includedReceived: (int) ($data['included_received'] ?? 50),
            usedReceived: (int) ($data['used_received'] ?? 0),
            receivedPriceCents: (int) ($data['received_price_cents'] ?? 5),
            receivedFree: (bool) ($data['received_free'] ?? false),
        );
    }
}
