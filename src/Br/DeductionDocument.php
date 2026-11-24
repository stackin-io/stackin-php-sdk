<?php

declare(strict_types=1);

namespace Stackin\Br;

use DateTimeInterface;

/**
 * One document behind the deduction (docDedRed, or docAjusteBC in the
 * homologation layout). Set exactly one of `nfseKey`, `nfeKey`, `cteKey`,
 * `municipalNfse`, `paperInvoice`, `fiscalDocument` and `otherDocument`.
 * `type` is the tpDedRed code: 1 to 9, 99, 101 to 105 or 199.
 */
final class DeductionDocument
{
    public readonly int $type;

    public readonly string $issueDate;

    public readonly string $totalAmount;

    public readonly string $deductedAmount;

    public readonly ?string $description;

    public readonly ?string $nfseKey;

    public readonly ?string $nfeKey;

    public readonly ?string $cteKey;

    public readonly ?MunicipalNfse $municipalNfse;

    public readonly ?PaperInvoice $paperInvoice;

    public readonly ?OtherFiscalDocument $fiscalDocument;

    public readonly ?OtherDocument $otherDocument;

    public readonly ?string $competenceDate;

    public readonly ?DeductionSupplier $supplier;

    public function __construct(
        int $type,
        DateTimeInterface|string $issueDate,
        float|string $totalAmount,
        float|string $deductedAmount,
        ?string $description = null,
        ?string $nfseKey = null,
        ?string $nfeKey = null,
        ?string $cteKey = null,
        ?MunicipalNfse $municipalNfse = null,
        ?PaperInvoice $paperInvoice = null,
        ?OtherFiscalDocument $fiscalDocument = null,
        ?OtherDocument $otherDocument = null,
        DateTimeInterface|string|null $competenceDate = null,
        ?DeductionSupplier $supplier = null,
    ) {
        $this->type = Check::requiredInteger(
            $type,
            'nfse.deductions.documents.type',
            [1, 2, 3, 4, 5, 6, 7, 8, 9, 99, 101, 102, 103, 104, 105, 199],
        );
        $this->issueDate = Check::requiredDay($issueDate, 'nfse.deductions.documents.issue_date');
        $this->totalAmount = Check::requiredDecimal(
            $totalAmount,
            'nfse.deductions.documents.total_amount',
            0,
            true,
            1000000000000000.0,
            2,
        );
        $this->deductedAmount = Check::requiredDecimal(
            $deductedAmount,
            'nfse.deductions.documents.deducted_amount',
            0,
            true,
            1000000000000000.0,
            2,
        );
        $this->description = Check::text(
            $description,
            'nfse.deductions.documents.description',
            1,
            150,
        );
        $this->nfseKey = Check::pattern(
            $nfseKey,
            'nfse.deductions.documents.nfse_key',
            '/^[0-9]{6}[0-9A-Z]{14}[0-9]{30}$/',
            'the 50-character access key of a national NFS-e',
        );
        $this->nfeKey = Check::pattern(
            $nfeKey,
            'nfse.deductions.documents.nfe_key',
            '/^[0-9]{6}[0-9A-Z]{14}[0-9]{24}$/',
            'the 44-character access key of an NF-e',
        );
        $this->cteKey = Check::pattern(
            $cteKey,
            'nfse.deductions.documents.cte_key',
            '/^[0-9]{6}[0-9A-Z]{14}[0-9]{24}$/',
            'the 44-character access key of a CT-e',
        );
        $this->municipalNfse = $municipalNfse;
        $this->paperInvoice = $paperInvoice;
        $this->fiscalDocument = $fiscalDocument;
        $this->otherDocument = $otherDocument;
        $this->competenceDate = Check::day(
            $competenceDate,
            'nfse.deductions.documents.competence_date',
        );
        $this->supplier = $supplier;

        $identified = Check::count(
            Check::has($this->nfseKey),
            Check::has($this->nfeKey),
            Check::has($this->cteKey),
            $this->municipalNfse !== null,
            $this->paperInvoice !== null,
            $this->fiscalDocument !== null,
            $this->otherDocument !== null,
        );
        if ($identified !== 1) {
            Check::fail(
                'nfse.deductions.documents',
                'takes exactly one of nfse_key, nfe_key, cte_key, municipal_nfse, ' .
                'paper_invoice, fiscal_document, other_document',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'type' => $this->type,
            'issue_date' => $this->issueDate,
            'total_amount' => $this->totalAmount,
            'deducted_amount' => $this->deductedAmount,
            'description' => $this->description,
            'nfse_key' => $this->nfseKey,
            'nfe_key' => $this->nfeKey,
            'cte_key' => $this->cteKey,
            'municipal_nfse' => $this->municipalNfse?->toArray(),
            'paper_invoice' => $this->paperInvoice?->toArray(),
            'fiscal_document' => $this->fiscalDocument?->toArray(),
            'other_document' => $this->otherDocument?->toArray(),
            'competence_date' => $this->competenceDate,
            'supplier' => $this->supplier?->toArray(),
        ]);
    }
}
