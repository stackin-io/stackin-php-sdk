<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * A paper service invoice (NFNFS).
 */
final class PaperInvoice
{
    public readonly string $number;

    public readonly string $model;

    public readonly string $series;

    public function __construct(
        string $number,
        string $model,
        string $series,
    ) {
        $this->number = Check::requiredPattern(
            $number,
            'nfse.deductions.documents.paper_invoice.number',
            '/^\d{1,7}$/',
            'up to 7 digits',
        );
        $this->model = Check::requiredPattern(
            $model,
            'nfse.deductions.documents.paper_invoice.model',
            '/^\d{1,15}$/',
            'up to 15 digits',
        );
        $this->series = Check::requiredPattern(
            $series,
            'nfse.deductions.documents.paper_invoice.series',
            '/^[A-Za-z0-9]{1,15}$/',
            'up to 15 letters or digits',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'number' => $this->number,
            'model' => $this->model,
            'series' => $this->series,
        ]);
    }
}
