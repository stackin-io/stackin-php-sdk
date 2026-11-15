<?php

declare(strict_types=1);

namespace Stackin\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stackin\Address;
use Stackin\Errors\InvoiceError;

final class AddressTest extends TestCase
{
    public function testEmptyAddressToArray(): void
    {
        $address = new Address();

        $this->assertSame([], $address->toArray());
    }

    public function testFullAddressToArray(): void
    {
        $address = new Address(
            state: 'SP',
            cityCode: '3550308',
            street: 'Rua das Flores',
            number: '123',
            neighborhood: 'Centro',
            city: 'Sao Paulo',
            zipCode: '01310100',
        );

        $this->assertSame([
            'state' => 'SP',
            'city_code' => '3550308',
            'street' => 'Rua das Flores',
            'number' => '123',
            'neighborhood' => 'Centro',
            'city' => 'Sao Paulo',
            'zip_code' => '01310100',
        ], $address->toArray());
    }

    public function testPartialAddressOmitsNullFields(): void
    {
        $address = new Address(state: 'SC');

        $this->assertSame(['state' => 'SC'], $address->toArray());
    }

    public function testAcceptsTwoCharacterStreetAndNeighborhood(): void
    {
        $address = new Address(street: 'Ru', neighborhood: ' Ce ');

        $this->assertSame('Ru', $address->street);
        $this->assertSame(' Ce ', $address->neighborhood);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function tooShort(): array
    {
        return [
            'one-char street' => ['street', 'R'],
            'whitespace-padded street' => ['street', '  R  '],
            'blank street' => ['street', '   '],
            'one-char neighborhood' => ['neighborhood', 'C'],
            'whitespace-padded neighborhood' => ['neighborhood', ' C '],
        ];
    }

    #[DataProvider('tooShort')]
    public function testRejectsStreetOrNeighborhoodUnderTwoCharacters(
        string $field,
        string $value,
    ): void {
        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage("{$field} must have at least 2 characters");

        new Address(...[$field => $value]);
    }
}
