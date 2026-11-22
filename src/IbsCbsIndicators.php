<?php

declare(strict_types=1);

namespace Stackin;

/**
 * The cClassTrib's group, reduction and deferral indicators; null where
 * the classification has none.
 */
final class IbsCbsIndicators
{
    public function __construct(
        public readonly ?int $group,
        public readonly ?int $reduction,
        public readonly ?int $deferral,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            group: isset($data['group']) ? (int) $data['group'] : null,
            reduction: isset($data['reduction']) ? (int) $data['reduction'] : null,
            deferral: isset($data['deferral']) ? (int) $data['deferral'] : null,
        );
    }
}
