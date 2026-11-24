<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * The dest group: who receives the service when it is not the tomador. Send
 * exactly one of `taxId`, `nif` or `noNifReason` ("0", "1" or "2").
 */
final class IbsCbsRecipient
{
    public readonly string $name;

    public readonly ?string $taxId;

    public readonly ?string $nif;

    public readonly ?string $noNifReason;

    public readonly ?IbsCbsRecipientAddress $address;

    public readonly ?string $phone;

    public readonly ?string $email;

    public function __construct(
        string $name,
        ?string $taxId = null,
        ?string $nif = null,
        ?string $noNifReason = null,
        ?IbsCbsRecipientAddress $address = null,
        ?string $phone = null,
        ?string $email = null,
    ) {
        $this->name = Check::required($name, 'ibs_cbs.recipient.name', 1, 150);
        $this->taxId = Check::pattern(
            $taxId,
            'ibs_cbs.recipient.tax_id',
            '/^(\d{11}|[0-9A-Z]{12}\d{2})$/',
            'a CPF (11 digits) or a CNPJ (14 characters)',
        );
        $this->nif = Check::text($nif, 'ibs_cbs.recipient.nif', 1, 40);
        $this->noNifReason = Check::choice(
            $noNifReason,
            'ibs_cbs.recipient.no_nif_reason',
            ['0', '1', '2'],
        );
        $this->address = $address;
        $this->phone = Check::pattern(
            $phone,
            'ibs_cbs.recipient.phone',
            '/^\d{6,20}$/',
            '6 to 20 digits',
        );
        $this->email = Check::text($email, 'ibs_cbs.recipient.email', 1, 80);

        $email = '/^[^@\s]+@[^@\s]+\.[^@\s]+$/';
        if ($this->email !== null && preg_match($email, $this->email) !== 1) {
            Check::fail('ibs_cbs.recipient.email', 'must be an e-mail address');
        }
        $ids = Check::count(
            $this->taxId !== null,
            $this->nif !== null,
            $this->noNifReason !== null,
        );
        if ($ids !== 1) {
            Check::fail('ibs_cbs.recipient', 'takes exactly one of tax_id, nif or no_nif_reason');
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
            'nif' => $this->nif,
            'no_nif_reason' => $this->noNifReason,
            'address' => $this->address?->toArray(),
            'phone' => $this->phone,
            'email' => $this->email,
        ]);
    }
}
