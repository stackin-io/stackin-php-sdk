<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * One property: identify it by `cib` (8 characters) or by `address`, one of
 * the two. `baseAdjustments` are 1 to 1000 and read by the homologation
 * layout only.
 */
final class RealEstateUnit
{
    public readonly ?string $propertyRegistration;

    public readonly ?string $cib;

    public readonly ?ServiceAddress $address;

    /** @var list<RealEstateBaseAdjustment>|null */
    public readonly ?array $baseAdjustments;

    /**
     * @param list<RealEstateBaseAdjustment>|null $baseAdjustments
     */
    public function __construct(
        ?string $propertyRegistration = null,
        ?string $cib = null,
        ?ServiceAddress $address = null,
        ?array $baseAdjustments = null,
    ) {
        $this->propertyRegistration = Check::text(
            $propertyRegistration,
            'ibs_cbs.real_estate.units.property_registration',
            1,
            30,
        );
        $this->cib = Check::pattern(
            $cib,
            'ibs_cbs.real_estate.units.cib',
            '/^[0-9A-Z]{8}$/',
            '8 characters',
        );
        $this->address = $address;
        $this->baseAdjustments = Check::objects(
            $baseAdjustments,
            'ibs_cbs.real_estate.units.base_adjustments',
            1000,
            RealEstateBaseAdjustment::class,
        );

        if (($this->cib !== null) === ($this->address !== null)) {
            Check::fail('ibs_cbs.real_estate.units', 'takes cib or address');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'property_registration' => $this->propertyRegistration,
            'cib' => $this->cib,
            'address' => $this->address?->toArray(),
            'base_adjustments' => Check::arrays($this->baseAdjustments),
        ]);
    }
}
