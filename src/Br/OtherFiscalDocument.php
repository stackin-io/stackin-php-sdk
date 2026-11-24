<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * A fiscal document outside the national repository. `cityCode` and
 * `description` are read by the homologation layout only.
 */
final class OtherFiscalDocument
{
    public readonly string $number;

    public readonly ?string $cityCode;

    public readonly ?string $description;

    public function __construct(
        string $number,
        ?string $cityCode = null,
        ?string $description = null,
    ) {
        $this->number = Check::required(
            $number,
            'nfse.deductions.documents.fiscal_document.number',
            1,
            255,
        );
        $this->cityCode = Check::pattern(
            $cityCode,
            'nfse.deductions.documents.fiscal_document.city_code',
            '/^\d{7}$/',
            '7 digits',
        );
        $this->description = Check::text(
            $description,
            'nfse.deductions.documents.fiscal_document.description',
            1,
            255,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'number' => $this->number,
            'city_code' => $this->cityCode,
            'description' => $this->description,
        ]);
    }
}
