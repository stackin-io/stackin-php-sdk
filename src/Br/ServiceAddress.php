<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * A Brazilian address of a construction, an event or a property: `zipCode`
 * (8 digits), `street`, `number` and `neighborhood` are required.
 */
final class ServiceAddress
{
    public readonly string $zipCode;

    public readonly string $street;

    public readonly string $number;

    public readonly string $neighborhood;

    public readonly ?string $complement;

    public function __construct(
        string $zipCode,
        string $street,
        string $number,
        string $neighborhood,
        ?string $complement = null,
    ) {
        $this->zipCode = Check::requiredPattern(
            $zipCode,
            'address.zip_code',
            '/^\d{8}$/',
            '8 digits',
        );
        $this->street = Check::required($street, 'address.street', 1, 255);
        $this->number = Check::required($number, 'address.number', 1, 60);
        $this->neighborhood = Check::required($neighborhood, 'address.neighborhood', 1, 60);
        $this->complement = Check::text($complement, 'address.complement', 1, 156);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'zip_code' => $this->zipCode,
            'street' => $this->street,
            'number' => $this->number,
            'neighborhood' => $this->neighborhood,
            'complement' => $this->complement,
        ]);
    }
}
