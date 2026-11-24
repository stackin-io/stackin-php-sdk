<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * The DPS interm group, identified by exactly one of `taxId` (CPF or CNPJ,
 * masks dropped), `foreignId` (NIF) or `foreignIdMissingReason` (1 exempt,
 * 2 not required). A `foreignId` or `foreignIdMissingReason` needs an
 * `address` abroad, and a `taxId` one in Brazil or none.
 */
final class NfseIntermediary
{
    public readonly string $name;

    public readonly ?string $taxId;

    public readonly ?string $foreignId;

    public readonly ?int $foreignIdMissingReason;

    public readonly ?string $caepf;

    public readonly ?string $municipalRegistration;

    public readonly ?PersonAddress $address;

    public readonly ?string $phone;

    public readonly ?string $email;

    public function __construct(
        string $name,
        ?string $taxId = null,
        ?string $foreignId = null,
        ?int $foreignIdMissingReason = null,
        ?string $caepf = null,
        ?string $municipalRegistration = null,
        ?PersonAddress $address = null,
        ?string $phone = null,
        ?string $email = null,
    ) {
        $this->name = Check::required($name, 'nfse.intermediary.name', 1, 300);
        $this->taxId = Check::pattern(
            Check::unmask($taxId, true),
            'nfse.intermediary.tax_id',
            '/^([0-9A-Z]{12}\d{2}|\d{11})$/',
            'a CPF (11 digits) or a CNPJ (14 characters)',
        );
        $this->foreignId = Check::text($foreignId, 'nfse.intermediary.foreign_id', 1, 40);
        $this->foreignIdMissingReason = Check::integer(
            $foreignIdMissingReason,
            'nfse.intermediary.foreign_id_missing_reason',
            [1, 2],
        );
        $this->caepf = Check::pattern($caepf, 'nfse.intermediary.caepf', '/^\d{14}$/', '14 digits');
        $this->municipalRegistration = Check::pattern(
            Check::unmask($municipalRegistration),
            'nfse.intermediary.municipal_registration',
            '/^[A-Za-z0-9]{1,15}$/',
            'up to 15 letters or digits',
        );
        $this->address = $address;
        $this->phone = Check::pattern(
            Check::phone($phone),
            'nfse.intermediary.phone',
            '/^\+?[0-9]{8,15}$/',
            '8 to 15 digits with an optional leading +',
        );
        $this->email = Check::pattern(
            $email,
            'nfse.intermediary.email',
            '/^[^@\s]+@[^@\s]+\.[^@\s]+$/',
            'an e-mail address',
        );

        $identifications = Check::count(
            Check::has($this->taxId),
            Check::has($this->foreignId),
            $this->foreignIdMissingReason !== null,
        );
        if ($identifications !== 1) {
            Check::fail(
                'nfse.intermediary',
                'takes exactly one of tax_id, foreign_id or foreign_id_missing_reason',
            );
        }
        $abroad = $this->address !== null && $this->address->isAbroad();
        if (!Check::has($this->taxId) && !$abroad) {
            Check::fail(
                'nfse.intermediary.address',
                'must be abroad (country_code, postal_code, city and state) for an ' .
                'intermediary with foreign_id or foreign_id_missing_reason',
            );
        }
        if (Check::has($this->taxId) && $abroad) {
            Check::fail(
                'nfse.intermediary.address',
                'abroad needs foreign_id or foreign_id_missing_reason, not tax_id',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'name' => $this->name,
            'tax_id' => $this->taxId,
            'foreign_id' => $this->foreignId,
            'foreign_id_missing_reason' => $this->foreignIdMissingReason,
            'caepf' => $this->caepf,
            'municipal_registration' => $this->municipalRegistration,
            'address' => $this->address?->toArray(),
            'phone' => $this->phone,
            'email' => $this->email,
        ]);
    }
}
