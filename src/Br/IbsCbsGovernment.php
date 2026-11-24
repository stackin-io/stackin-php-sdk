<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * tpOper and tpEnteGov: `operationType` "1" to "5" and `entityType` "1" to
 * "4". Send at least one.
 */
final class IbsCbsGovernment
{
    public readonly ?string $operationType;

    public readonly ?string $entityType;

    public function __construct(
        ?string $operationType = null,
        ?string $entityType = null,
    ) {
        $this->operationType = Check::choice(
            $operationType,
            'ibs_cbs.government.operation_type',
            ['1', '2', '3', '4', '5'],
        );
        $this->entityType = Check::choice(
            $entityType,
            'ibs_cbs.government.entity_type',
            ['1', '2', '3', '4'],
        );

        if ($this->operationType === null && $this->entityType === null) {
            Check::fail('ibs_cbs.government', 'needs operation_type, entity_type or both');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'operation_type' => $this->operationType,
            'entity_type' => $this->entityType,
        ]);
    }
}
