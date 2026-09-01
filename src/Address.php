<?php

declare(strict_types=1);

namespace Stackin;

/**
 * A plain postal address.
 */
final class Address
{
    public function __construct(
        public readonly ?string $state = null,
        public readonly ?string $cityCode = null,
        public readonly ?string $street = null,
        public readonly ?string $number = null,
        public readonly ?string $neighborhood = null,
        public readonly ?string $city = null,
        public readonly ?string $zipCode = null,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $data = [
            'state' => $this->state,
            'city_code' => $this->cityCode,
            'street' => $this->street,
            'number' => $this->number,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'zip_code' => $this->zipCode,
        ];

        return array_filter($data, static fn (?string $value): bool => $value !== null);
    }
}
