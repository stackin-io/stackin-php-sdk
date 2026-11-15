<?php

declare(strict_types=1);

namespace Stackin;

use Stackin\Errors\InvoiceError;

/**
 * A plain postal address.
 *
 * `street` and `neighborhood`, when given, must have at least 2
 * characters after trimming — the NF-e schema (xLgr/xBairro) refuses a
 * shorter one. Raises InvoiceError when built otherwise.
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
        foreach (['street' => $street, 'neighborhood' => $neighborhood] as $field => $value) {
            if ($value !== null && $value !== '' && mb_strlen(trim($value)) < 2) {
                throw new InvoiceError("{$field} must have at least 2 characters");
            }
        }
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
