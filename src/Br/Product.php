<?php

declare(strict_types=1);

namespace Stackin\Br;

use Stackin\Errors\InvoiceError;

/**
 * One product or service line item on an invoice.
 *
 * `description` plus either `unitPrice` or `amount` apply to any
 * document type. The rest are
 * Brazil-specific and required per item only when document_type is
 * NFE — ignored for NFSE.
 *
 * Field formats are checked when the item is built and raise
 * InvoiceError: `ncm` must be the full 8 digits, or 00 for a service
 * line (SEFAZ rejects any other 2-digit chapter with 777), and
 * `barcode` (cEAN) must be `SEM GTIN` or a GTIN of 8, 12, 13 or 14
 * digits whose GS1 check digit is right. `recopiNumber` must be the
 * 20-digit RECOPI number with its two modulo-11 check digits right.
 *
 * `fuel` is the NF-e fuel group (`comb`), sent under `br.fuel`. It is the
 * one way to send that group: `extraGroups` carrying a `comb` (or a
 * `fuel`) next to it is refused.
 *
 * `itemId` names an item of the company's catalog (see Items): it fills
 * whatever the line leaves out (description, unit price, unit, barcode,
 * ncm, cfop, cest, tax, service code, ISS retention), and what the line
 * sends wins (`unit` and `taxRetained` default to `UN` and false only
 * without it). With it `description` is optional, and the NF-e's ncm and
 * cfop are not required here: the API checks the filled line.
 *
 * `ibsCbs` is the IBS/CBS group, sent under `br.ibs_cbs`: an `IbsCbs`, or
 * the same keys as a plain array.
 */
final class Product
{
    public function __construct(
        public readonly ?string $description = null,
        public readonly ?float $amount = null,
        public readonly ?string $unit = null,
        public readonly float $quantity = 1.0,
        public readonly ?string $barcode = null,
        public readonly ?float $freight = null,
        public readonly ?float $insurance = null,
        public readonly ?float $discount = null,
        public readonly ?float $otherExpenses = null,
        public readonly bool $usedMovableAsset = false,
        public readonly ?string $purchaseOrder = null,
        public readonly ?string $purchaseOrderItem = null,
        public readonly ?string $ncm = null,
        public readonly ?string $cfop = null,
        public readonly ?string $cest = null,
        /** @var string[]|null */
        public readonly ?array $nveCodes = null,
        public readonly ?string $indEscala = null,
        public readonly ?string $manufacturerCnpj = null,
        public readonly ?string $taxBenefitCode = null,
        /** @var array<int, array{code: string, percentage: float, amount: float}>|null */
        public readonly ?array $presumedCredits = null,
        public readonly ?string $exTipi = null,
        public readonly ?string $importContentControlNumber = null,
        public readonly ?string $recopiNumber = null,
        /** @var array<string, mixed>|null */
        public readonly ?array $extraGroups = null,
        /** @var array<string, mixed>|null */
        public readonly ?array $tax = null,
        public readonly ?string $serviceCode = null,
        public readonly ?float $serviceDiscount = null,
        public readonly ?bool $taxRetained = null,
        public readonly ?string $observations = null,
        public readonly ?float $unitPrice = null,
        /** @var IbsCbs|array<string, mixed>|null */
        public readonly IbsCbs|array|null $ibsCbs = null,
        public readonly ?Fuel $fuel = null,
        public readonly ?string $itemId = null,
    ) {
        if ($description === null && $itemId === null) {
            throw new InvoiceError('description is required unless itemId is given');
        }
        if ($fuel !== null && $extraGroups !== null && self::carriesFuelGroup($extraGroups)) {
            throw new InvoiceError('send the fuel group once: fuel or extraGroups comb');
        }
        if ($ncm !== null && preg_match('/^(00|\d{8})$/', $ncm) !== 1) {
            throw new InvoiceError(
                'ncm must be the full 8 digits, or 00 for a service line; '
                . 'SEFAZ rejects any other code (777)',
            );
        }
        if ($barcode !== null && preg_match('/^(SEM GTIN|\d{8}|\d{12,14})$/', $barcode) !== 1) {
            throw new InvoiceError(
                "barcode must be 'SEM GTIN' or a GTIN of 8, 12, 13 or 14 digits",
            );
        }
        if ($barcode !== null && $barcode !== 'SEM GTIN' && !self::validGtinCheckDigit($barcode)) {
            throw new InvoiceError('barcode has an invalid GTIN check digit');
        }
        if ($recopiNumber !== null && preg_match('/^\d{20}\z/', $recopiNumber) !== 1) {
            throw new InvoiceError('recopiNumber must have exactly 20 digits');
        }
        if ($recopiNumber !== null && !self::validRecopiCheckDigits($recopiNumber)) {
            throw new InvoiceError('recopiNumber has invalid check digits');
        }
    }

    /**
     * Whether the passthrough groups already hold the fuel group, either
     * merged straight into `br` or under its `extra_groups`.
     *
     * @param array<string, mixed> $extraGroups
     */
    private static function carriesFuelGroup(array $extraGroups): bool
    {
        $nested = $extraGroups['extra_groups'] ?? null;

        return array_key_exists('comb', $extraGroups)
            || array_key_exists('fuel', $extraGroups)
            || (is_array($nested) && array_key_exists('comb', $nested));
    }

    /**
     * RECOPI (MOC 7.0 §8.5): two modulo-11 check digits. DV1 over the
     * first 18 digits with weights 18..1 left to right, DV2 over the
     * first 19 (with DV1) with weights 19..1; r = sum % 11, DV = 0 when
     * r is 0 or 1, else 11 - r. The date/time part is not checked.
     */
    private static function validRecopiCheckDigits(string $recopi): bool
    {
        $digit = static function (string $body): int {
            $sum = 0;
            $length = strlen($body);
            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $body[$i] * ($length - $i);
            }
            $r = $sum % 11;

            return $r < 2 ? 0 : 11 - $r;
        };

        return $digit(substr($recopi, 0, 18)) === (int) $recopi[18]
            && $digit(substr($recopi, 0, 19)) === (int) $recopi[19];
    }

    /**
     * GS1 modulo-10: from the rightmost digit before the check digit,
     * weights alternate 3, 1, 3, 1...; check = (10 - sum % 10) % 10.
     */
    private static function validGtinCheckDigit(string $gtin): bool
    {
        $body = substr($gtin, 0, -1);
        $sum = 0;
        $weight = 3;
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum += (int) $body[$i] * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return (10 - $sum % 10) % 10 === (int) substr($gtin, -1);
    }

    private static function decimalString(?float $value): ?string
    {
        return $value === null ? null : rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
    }

    /**
     * Returns the item as a plain array, ready for the request body.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = array_filter(
            [
                'unit' => $this->unit ?? ($this->itemId === null ? 'UN' : null),
                'quantity' => $this->quantity,
                'used_movable_asset' => $this->usedMovableAsset,
                'barcode' => $this->barcode,
                'freight' => $this->freight,
                'insurance' => $this->insurance,
                'discount' => $this->discount,
                'other_expenses' => $this->otherExpenses,
                'purchase_order' => $this->purchaseOrder,
                'purchase_order_item' => $this->purchaseOrderItem,
            ],
            static fn (mixed $value): bool => $value !== null,
        );

        $br = array_filter(
            [
                'ncm' => $this->ncm,
                'cfop' => $this->cfop,
                'cest' => $this->cest,
                'nve_codes' => $this->nveCodes,
                'ind_escala' => $this->indEscala,
                'manufacturer_cnpj' => $this->manufacturerCnpj,
                'tax_benefit_code' => $this->taxBenefitCode,
                'presumed_credits' => $this->presumedCredits,
                'ex_tipi' => $this->exTipi,
                'import_content_control_number' => $this->importContentControlNumber,
                'recopi_number' => $this->recopiNumber,
                'tax' => $this->tax,
                'ibs_cbs' => $this->ibsCbs instanceof IbsCbs
                    ? $this->ibsCbs->toArray()
                    : $this->ibsCbs,
                'fuel' => $this->fuel?->toArray(),
            ],
            static fn (mixed $value): bool => $value !== null,
        );

        if ($this->extraGroups !== null) {
            $br = self::withExtraGroups($br, $this->extraGroups);
        }

        if ($br !== []) {
            $data['br'] = $br;
        }

        $result = array_filter(
            [
                'item_id' => $this->itemId,
                'description' => $this->description,
                'amount' => self::decimalString($this->amount),
                'unit_price' => self::decimalString($this->unitPrice),
                'product' => $data,
                'service_code' => $this->serviceCode,
                'discount' => $this->serviceDiscount,
                'observations' => $this->observations,
            ],
            static fn (mixed $value): bool => $value !== null,
        );
        // Left unset on a catalog line, so the item's iss_retained applies.
        if ($this->taxRetained !== null || $this->itemId === null) {
            $result['tax_retained'] = $this->taxRetained ?? false;
        }

        return $result;
    }

    /**
     * Keys the API reads directly on `br`; any other key is a layout
     * group (veicProd, med, arma, comb, DI, rastro...) and goes under
     * `br.extra_groups`, the only place the API accepts it.
     */
    private const BR_FIELDS = [
        'ncm', 'cfop', 'cest', 'nve_codes', 'ind_escala', 'manufacturer_cnpj',
        'tax_benefit_code', 'presumed_credits', 'ex_tipi',
        'import_content_control_number', 'recopi_number', 'ibs_cbs', 'tax',
        'fuel', 'extra_groups',
    ];

    /**
     * @param array<string, mixed> $br
     * @param array<string, mixed> $extraGroups
     * @return array<string, mixed>
     */
    private static function withExtraGroups(array $br, array $extraGroups): array
    {
        $groups = [];
        foreach ($extraGroups as $key => $value) {
            if (in_array($key, self::BR_FIELDS, true)) {
                $br[$key] = $value;
            } else {
                $groups[$key] = $value;
            }
        }
        if ($groups !== []) {
            $br['extra_groups'] = [...($br['extra_groups'] ?? []), ...$groups];
        }

        return $br;
    }
}
