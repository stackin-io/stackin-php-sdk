<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * A non-fiscal document. `description` is read by the homologation layout
 * only.
 */
final class OtherDocument
{
    public readonly string $number;

    public readonly ?string $description;

    public function __construct(
        string $number,
        ?string $description = null,
    ) {
        $this->number = Check::required(
            $number,
            'nfse.deductions.documents.other_document.number',
            1,
            255,
        );
        $this->description = Check::text(
            $description,
            'nfse.deductions.documents.other_document.description',
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
            'description' => $this->description,
        ]);
    }
}
