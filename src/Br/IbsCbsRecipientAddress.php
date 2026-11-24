<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * The dest/end address: in Brazil (`cityCode` and `zipCode`) or abroad
 * (`countryCode`, `postalCode`, `city` and `region`), never both. `street`,
 * `number` and `neighborhood` are required.
 */
final class IbsCbsRecipientAddress
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

    public readonly ?string $region;

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
        ?string $region = null,
    ) {
        $this->street = Check::required($street, 'ibs_cbs.recipient.address.street', 1, 255);
        $this->number = Check::required($number, 'ibs_cbs.recipient.address.number', 1, 60);
        $this->neighborhood = Check::required(
            $neighborhood,
            'ibs_cbs.recipient.address.neighborhood',
            1,
            60,
        );
        $this->complement = Check::text(
            $complement,
            'ibs_cbs.recipient.address.complement',
            1,
            156,
        );
        $this->cityCode = Check::pattern(
            $cityCode,
            'ibs_cbs.recipient.address.city_code',
            '/^\d{7}$/',
            '7 digits',
        );
        $this->zipCode = Check::pattern(
            $zipCode,
            'ibs_cbs.recipient.address.zip_code',
            '/^\d{8}$/',
            '8 digits',
        );
        $this->countryCode = Check::pattern(
            $countryCode,
            'ibs_cbs.recipient.address.country_code',
            '/^[A-Z]{2}$/',
            'an ISO 3166 alpha-2 code',
        );
        $this->postalCode = Check::text(
            $postalCode,
            'ibs_cbs.recipient.address.postal_code',
            1,
            11,
        );
        $this->city = Check::text($city, 'ibs_cbs.recipient.address.city', 1, 60);
        $this->region = Check::text($region, 'ibs_cbs.recipient.address.region', 1, 60);

        $national = $this->cityCode !== null || $this->zipCode !== null;
        $foreign = $this->countryCode !== null || $this->postalCode !== null
            || $this->city !== null || $this->region !== null;
        if ($national && $foreign) {
            Check::fail(
                'ibs_cbs.recipient.address',
                'is in Brazil (city_code, zip_code) or abroad (country_code, ' .
                'postal_code, city, region), not both',
            );
        }
        $complete = $this->countryCode !== null && $this->postalCode !== null
            && $this->city !== null && $this->region !== null;
        if ($foreign && !$complete) {
            Check::fail(
                'ibs_cbs.recipient.address',
                'abroad needs country_code, postal_code, city and region',
            );
        }
        if (!$foreign && ($this->cityCode === null || $this->zipCode === null)) {
            Check::fail('ibs_cbs.recipient.address', 'in Brazil needs city_code and zip_code');
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
            'region' => $this->region,
        ]);
    }
}
