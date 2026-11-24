<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * A person's address: in Brazil (`cityCode` and `zipCode`) or abroad
 * (`countryCode`, `postalCode`, `city` and `state`), never both. `street`,
 * `number` and `neighborhood` are required. Used for a deduction supplier and
 * for the intermediary.
 */
final class PersonAddress
{
    public readonly string $street;

    public readonly string $number;

    public readonly string $neighborhood;

    public readonly ?string $complement;

    public readonly ?string $cityCode;

    public readonly ?string $zipCode;

    public readonly ?string $countryCode;

    public readonly ?string $postalCode;

    public readonly ?string $city;

    public readonly ?string $state;

    public function __construct(
        string $street,
        string $number,
        string $neighborhood,
        ?string $complement = null,
        ?string $cityCode = null,
        ?string $zipCode = null,
        ?string $countryCode = null,
        ?string $postalCode = null,
        ?string $city = null,
        ?string $state = null,
    ) {
        $this->street = Check::required($street, 'address.street', 1, 255);
        $this->number = Check::required($number, 'address.number', 1, 60);
        $this->neighborhood = Check::required($neighborhood, 'address.neighborhood', 1, 60);
        $this->complement = Check::text($complement, 'address.complement', 1, 156);
        $this->cityCode = Check::pattern($cityCode, 'address.city_code', '/^\d{7}$/', '7 digits');
        $this->zipCode = Check::pattern($zipCode, 'address.zip_code', '/^\d{8}$/', '8 digits');
        $this->countryCode = Check::pattern(
            $countryCode,
            'address.country_code',
            '/^[A-Z]{2}$/',
            'an ISO 3166 alpha-2 code',
        );
        $this->postalCode = Check::text($postalCode, 'address.postal_code', 1, 11);
        $this->city = Check::text($city, 'address.city', 1, 60);
        $this->state = Check::text($state, 'address.state', 1, 60);

        $national = Check::has($this->cityCode) && Check::has($this->zipCode)
            && !Check::has($this->countryCode) && !Check::has($this->postalCode)
            && !Check::has($this->city) && !Check::has($this->state);
        $foreign = Check::has($this->countryCode) && Check::has($this->postalCode)
            && Check::has($this->city) && Check::has($this->state)
            && !Check::has($this->cityCode) && !Check::has($this->zipCode);
        if ($foreign && $this->countryCode === 'BR') {
            Check::fail('address.country_code', 'is for an address abroad and cannot be BR');
        }
        if (!$national && !$foreign) {
            Check::fail(
                'address',
                'takes city_code and zip_code for an address in Brazil, or ' .
                'country_code, postal_code, city and state for one abroad',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'street' => $this->street,
            'number' => $this->number,
            'neighborhood' => $this->neighborhood,
            'complement' => $this->complement,
            'city_code' => $this->cityCode,
            'zip_code' => $this->zipCode,
            'country_code' => $this->countryCode,
            'postal_code' => $this->postalCode,
            'city' => $this->city,
            'state' => $this->state,
        ]);
    }

    public function isAbroad(): bool
    {
        return Check::has($this->countryCode);
    }
}
