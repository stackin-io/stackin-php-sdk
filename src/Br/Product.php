<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * One product or service line item on an invoice.
 *
 * `description`/`amount` apply to any document type. The rest are
 * Brazil-specific and required per item only when document_type is
 * NFE — ignored for NFSE.
 */
final class Product
{
    public function __construct(
        public readonly string $description,
        public readonly float $amount,
        public readonly string $unit = 'UN',
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
    ) {
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
                'unit' => $this->unit,
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
            ],
            static fn (mixed $value): bool => $value !== null,
        );

        if ($this->extraGroups !== null) {
            $br = [...$br, ...$this->extraGroups];
        }

        if ($br !== []) {
            $data['br'] = $br;
        }

        return [
            'description' => $this->description,
            'amount' => $this->amount,
            'product' => $data,
        ];
    }
}
