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

    public function testStateRegistrationIsOmittedWhenAbsent(): void
    {
        $address = new Address(state: 'SP');

        $this->assertNull($address->stateRegistration);
        $this->assertArrayNotHasKey('state_registration', $address->toArray());
    }

    public function testStateRegistrationIsentoIsAccepted(): void
    {
        $address = new Address(state: 'SP', stateRegistration: 'isento');

        $this->assertSame('ISENTO', $address->stateRegistration);
        $this->assertSame('ISENTO', $address->toArray()['state_registration']);
    }

    public function testStateRegistrationDropsTheMask(): void
    {
        $address = new Address(state: 'SP', stateRegistration: '110.042.490.114');

        $this->assertSame('110042490114', $address->stateRegistration);
        $this->assertSame('110042490114', $address->toArray()['state_registration']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badStateRegistration(): array
    {
        return [
            'one digit' => ['1'],
            'fifteen digits' => ['123456789012345'],
            'letters' => ['ABC123'],
            'blank' => ['   '],
            'isento with a suffix' => ['ISENTO1'],
        ];
    }

    #[DataProvider('badStateRegistration')]
    public function testRejectsAnInvalidStateRegistration(string $value): void
    {
        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage('stateRegistration takes 2 to 14 digits, or ISENTO');

        new Address(stateRegistration: $value);
    }

    public function testCountryFieldsToArray(): void
    {
        $address = new Address(state: 'EX', countryCode: '2496', countryName: 'Estados Unidos');

        $this->assertSame([
            'state' => 'EX',
            'country_code' => '2496',
            'country_name' => 'Estados Unidos',
        ], $address->toArray());
    }

    public function testCountryFieldsAreLeftOutWhenUnset(): void
    {
        $address = new Address(state: 'SC', zipCode: '89201100');

        $this->assertSame(['state' => 'SC', 'zip_code' => '89201100'], $address->toArray());
    }

    public function testDoesNotCheckTheBacenCheckDigit(): void
    {
        $address = new Address(state: 'EX', countryCode: '0001');

        $this->assertSame('0001', $address->countryCode);
    }

    public function testStateExIsAbroadInAnyCase(): void
    {
        $this->assertTrue((new Address(state: 'EX'))->isAbroad());
        $this->assertTrue((new Address(state: 'ex'))->isAbroad());
        $this->assertFalse((new Address(state: 'SC'))->isAbroad());
        $this->assertFalse((new Address())->isAbroad());
    }
}
