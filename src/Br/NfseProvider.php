<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * The DPS prest contact the issuing company adds: `caepf` (14 digits),
 * `phone` (8 to 15 digits with an optional leading +, masks dropped) and
 * `email`, all optional.
 */
final class NfseProvider
{
    public readonly ?string $caepf;

    public readonly ?string $phone;

    public readonly ?string $email;

    public function __construct(
        ?string $caepf = null,
        ?string $phone = null,
        ?string $email = null,
    ) {
        $this->caepf = Check::pattern($caepf, 'nfse.provider.caepf', '/^\d{14}$/', '14 digits');
        $this->phone = Check::pattern(
            Check::phone($phone),
            'nfse.provider.phone',
            '/^\+?[0-9]{8,15}$/',
            '8 to 15 digits with an optional leading +',
        );
        $this->email = Check::pattern(
            $email,
            'nfse.provider.email',
            '/^[^@\s]+@[^@\s]+\.[^@\s]+$/',
            'an e-mail address',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'caepf' => $this->caepf,
            'phone' => $this->phone,
            'email' => $this->email,
        ]);
    }
}
