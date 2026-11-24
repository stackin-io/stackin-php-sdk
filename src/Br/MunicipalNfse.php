<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * A municipal NFS-e outside the national repository.
 */
final class MunicipalNfse
{
    public readonly string $cityCode;

    public readonly string $number;

    public readonly string $verificationCode;

    public function __construct(
        string $cityCode,
        string $number,
        string $verificationCode,
    ) {
        $this->cityCode = Check::requiredPattern(
            $cityCode,
            'nfse.deductions.documents.municipal_nfse.city_code',
            '/^\d{7}$/',
            '7 digits',
        );
        $this->number = Check::requiredPattern(
            $number,
            'nfse.deductions.documents.municipal_nfse.number',
            '/^\d{1,15}$/',
            'up to 15 digits',
        );
        $this->verificationCode = Check::requiredPattern(
            $verificationCode,
            'nfse.deductions.documents.municipal_nfse.verification_code',
            '/^[A-Za-z0-9]{1,9}$/',
            'up to 9 letters or digits',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'city_code' => $this->cityCode,
            'number' => $this->number,
            'verification_code' => $this->verificationCode,
        ]);
    }
}
