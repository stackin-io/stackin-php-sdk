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
 *
 * A buyer abroad is `state` EX (NF-e only): `zipCode` and `cityCode` may
 * be left out, and `countryCode` (the BACEN code, 1 to 4 digits, not
 * 1058) with `countryName` name the country. The BACEN check digit is not
 * checked: the official table has countries whose digit does not close.
 *
 * `complement` is the apartment, suite or block (xCpl); one too long for the
 * layout is left out by the API with the warning RECIPIENT_COMPLEMENT_OMITTED.
 * `countryIso2` (ISO 3166 alpha-2, never BR), `postalCode` (up to 11
 * characters) and `province` (up to 60) are the address of an NFS-e tomador
 * abroad (`state` EX), NFS-e only.
 *
 * `stateRegistration` is the recipient's IE, NF-e only: 2 to 14 digits for an
 * ICMS contributor, `ISENTO` for an exempt one, left out for a
 * non-contributor. Spaces, dots, dashes and slashes are dropped.
 */
final class Address
{
    public readonly ?string $stateRegistration;

    public function __construct(
        public readonly ?string $state = null,
        public readonly ?string $cityCode = null,
        public readonly ?string $street = null,
        public readonly ?string $number = null,
        public readonly ?string $neighborhood = null,
        public readonly ?string $city = null,
        public readonly ?string $zipCode = null,
        public readonly ?string $countryCode = null,
        public readonly ?string $countryName = null,
        public readonly ?string $complement = null,
        public readonly ?string $countryIso2 = null,
        public readonly ?string $postalCode = null,
        public readonly ?string $province = null,
        ?string $stateRegistration = null,
    ) {
        foreach (['street' => $street, 'neighborhood' => $neighborhood] as $field => $value) {
            if ($value !== null && $value !== '' && mb_strlen(trim($value)) < 2) {
                throw new InvoiceError("{$field} must have at least 2 characters");
            }
        }
        if ($countryIso2 !== null && preg_match('/^[A-Z]{2}$/', $countryIso2) !== 1) {
            throw new InvoiceError('countryIso2 must be an ISO 3166 alpha-2 code');
        }
        if ($postalCode !== null && mb_strlen($postalCode) > 11) {
            throw new InvoiceError('postalCode must be at most 11 characters');
        }
        if ($province !== null && mb_strlen($province) > 60) {
            throw new InvoiceError('province must be at most 60 characters');
        }
        $this->stateRegistration = self::cleanStateRegistration($stateRegistration);
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
            'country_code' => $this->countryCode,
            'country_name' => $this->countryName,
            'complement' => $this->complement,
            'country_iso2' => $this->countryIso2,
            'postal_code' => $this->postalCode,
            'province' => $this->province,
            'state_registration' => $this->stateRegistration,
        ];

        return array_filter($data, static fn (?string $value): bool => $value !== null);
    }

    /**
     * Whether this is a buyer abroad: `state` EX, in any case.
     */
    public function isAbroad(): bool
    {
        return $this->state !== null && strtoupper($this->state) === 'EX';
    }

    private static function cleanStateRegistration(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $cleaned = strtoupper((string) preg_replace('/[\s.\/-]/', '', $value));
        if ($cleaned !== 'ISENTO' && preg_match('/^[0-9]{2,14}$/', $cleaned) !== 1) {
            throw new InvoiceError('stateRegistration takes 2 to 14 digits, or ISENTO');
        }

        return $cleaned;
    }
}
