<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * The DPS obra group. Identify the work by `workId` with `workIdType`
 * ("cno" or "cib", a CIB being exactly 8 characters) or by `address`, not
 * both. `propertyRegistration` is optional and not enough alone.
 */
final class Construction
{
    public readonly ?string $propertyRegistration;

    public readonly ?string $workIdType;

    public readonly ?string $workId;

    public readonly ?ServiceAddress $address;

    public function __construct(
        ?string $propertyRegistration = null,
        ?string $workIdType = null,
        ?string $workId = null,
        ?ServiceAddress $address = null,
    ) {
        $this->propertyRegistration = Check::text(
            $propertyRegistration,
            'nfse.construction.property_registration',
            1,
            30,
        );
        $this->workIdType = Check::choice(
            $workIdType,
            'nfse.construction.work_id_type',
            ['cno', 'cib'],
        );
        $this->workId = Check::text($workId, 'nfse.construction.work_id', 1, 30);
        $this->address = $address;

        if (Check::has($this->workId) !== Check::has($this->workIdType)) {
            Check::fail(
                'nfse.construction',
                'work_id and work_id_type go together: cno or cib, and the number',
            );
        }
        if (!Check::has($this->workId) && $this->address === null) {
            Check::fail(
                'nfse.construction',
                'needs work_id with work_id_type (CNO or CIB) or an address',
            );
        }
        if (Check::has($this->workId) && $this->address !== null) {
            Check::fail('nfse.construction', 'takes work_id or address, not both');
        }
        if ($this->workIdType === 'cib' && mb_strlen((string) $this->workId) !== 8) {
            Check::fail('nfse.construction.work_id', 'must have exactly 8 characters for a cib');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'property_registration' => $this->propertyRegistration,
            'work_id_type' => $this->workIdType,
            'work_id' => $this->workId,
            'address' => $this->address?->toArray(),
        ]);
    }
}
