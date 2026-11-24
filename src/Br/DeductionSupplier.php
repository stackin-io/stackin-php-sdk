<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * fornec, who issued the deducted document: `taxId` (CPF or CNPJ, masks
 * dropped) with an address in Brazil, or `foreignId` (NIF) with an address
 * abroad. Exactly one of the two.
 */
final class DeductionSupplier
{
    public readonly string $name;

    public readonly PersonAddress $address;

    public readonly ?string $taxId;

    public readonly ?string $foreignId;

    public function __construct(
        string $name,
        PersonAddress $address,
        ?string $taxId = null,
        ?string $foreignId = null,
    ) {
        $this->name = Check::required($name, 'nfse.deductions.supplier.name', 1, 150);
        $this->address = $address;
        $this->taxId = Check::pattern(
            Check::unmask($taxId),
            'nfse.deductions.supplier.tax_id',
            '/^(\d{11}|\d{14})$/',
            'a CPF (11 digits) or a CNPJ (14 digits)',
        );
        $this->foreignId = Check::text($foreignId, 'nfse.deductions.supplier.foreign_id', 1, 40);

        if (Check::has($this->taxId) === Check::has($this->foreignId)) {
            Check::fail(
                'nfse.deductions.supplier',
                'takes tax_id for a supplier in Brazil or foreign_id for one abroad, ' .
                'exactly one of them',
            );
        }
        if (Check::has($this->taxId) && $this->address->isAbroad()) {
            Check::fail(
                'nfse.deductions.supplier.address',
                'must be in Brazil, city_code and zip_code, for a supplier with tax_id',
            );
        }
        if (Check::has($this->foreignId) && !$this->address->isAbroad()) {
            Check::fail(
                'nfse.deductions.supplier.address',
                'must be abroad, country_code, postal_code, city and state, for a ' .
                'supplier with foreign_id',
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
            'address' => $this->address->toArray(),
            'tax_id' => $this->taxId,
            'foreign_id' => $this->foreignId,
        ]);
    }
}
