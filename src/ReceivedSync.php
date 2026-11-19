<?php

declare(strict_types=1);

namespace Stackin;

use DateTimeImmutable;

/**
 * What one received-invoice collection did, from
 * POST /received-invoices/sync.
 *
 * stored is how many new documents were saved across pages reads of
 * the SEFAZ distribution, lastNsu where the read stopped and maxNsu the
 * highest the SEFAZ reports. nextSyncAt is the earliest moment another
 * sync is accepted, when the SEFAZ imposed its one-hour wait.
 */
final class ReceivedSync
{
    public function __construct(
        public readonly int $stored,
        public readonly int $pages,
        public readonly string $lastNsu,
        public readonly ?string $maxNsu = null,
        public readonly ?DateTimeImmutable $nextSyncAt = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $max = $data['max_nsu'] ?? null;
        $next = $data['next_sync_at'] ?? null;

        return new self(
            stored: (int) ($data['stored'] ?? 0),
            pages: (int) ($data['pages'] ?? 0),
            lastNsu: (string) ($data['last_nsu'] ?? ''),
            maxNsu: $max !== null ? (string) $max : null,
            nextSyncAt: is_string($next) && $next !== '' ? new DateTimeImmutable($next) : null,
        );
    }
}
