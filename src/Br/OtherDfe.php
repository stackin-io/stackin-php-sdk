<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * A national-repository electronic document that is not an NFS-e, NF-e or
 * CT-e (dFeNacional with tipoChaveDFe 9). `key` is the chaveDFe, up to 50
 * characters, and `description` says what the document is.
 */
final class OtherDfe
{
    public readonly string $key;

    public readonly string $description;

    public function __construct(string $key, string $description)
    {
        $this->key = Check::required($key, 'nfse.deductions.documents.other_dfe.key', 1, 50);
        $this->description = Check::required(
            $description,
            'nfse.deductions.documents.other_dfe.description',
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
            'key' => $this->key,
            'description' => $this->description,
        ]);
    }
}
