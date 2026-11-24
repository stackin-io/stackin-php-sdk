<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * The imovel group. `cityCode` and `lease` are read by the homologation
 * layout only; `units` are 1 to 99, and the production layout takes exactly
 * one.
 */
final class RealEstate
{
    public readonly ?string $cityCode;

    public readonly ?RealEstateLease $lease;

    /** @var list<RealEstateUnit>|null */
    public readonly ?array $units;

    /**
     * @param list<RealEstateUnit>|null $units
     */
    public function __construct(
        ?string $cityCode = null,
        ?RealEstateLease $lease = null,
        ?array $units = null,
    ) {
        $this->cityCode = Check::pattern(
            $cityCode,
            'ibs_cbs.real_estate.city_code',
            '/^\d{7}$/',
            '7 digits',
        );
        $this->lease = $lease;
        $this->units = Check::objects(
            $units,
            'ibs_cbs.real_estate.units',
            99,
            RealEstateUnit::class,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'city_code' => $this->cityCode,
            'lease' => $this->lease?->toArray(),
            'units' => Check::arrays($this->units),
        ]);
    }
}
