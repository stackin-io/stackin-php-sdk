<?php

declare(strict_types=1);

namespace Stackin;

/**
 * The IBS/CBS amounts of one item or of the whole simulation, as the
 * API's decimal strings (such as "1.00"), never rounded to a float.
 */
final class IbsCbsAmounts
{
    public function __construct(
        public readonly string $base,
        public readonly string $ibsState,
        public readonly string $ibsCity,
        public readonly string $ibs,
        public readonly string $cbs,
        public readonly string $deferredIbsState,
        public readonly string $deferredIbsCity,
        public readonly string $deferredCbs,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            base: (string) ($data['base'] ?? '0.00'),
            ibsState: (string) ($data['ibs_state'] ?? '0.00'),
            ibsCity: (string) ($data['ibs_city'] ?? '0.00'),
            ibs: (string) ($data['ibs'] ?? '0.00'),
            cbs: (string) ($data['cbs'] ?? '0.00'),
            deferredIbsState: (string) ($data['deferred_ibs_state'] ?? '0.00'),
            deferredIbsCity: (string) ($data['deferred_ibs_city'] ?? '0.00'),
            deferredCbs: (string) ($data['deferred_cbs'] ?? '0.00'),
        );
    }
}
