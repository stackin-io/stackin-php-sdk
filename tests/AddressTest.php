<?php

declare(strict_types=1);

namespace Stackin\Tests;

use PHPUnit\Framework\TestCase;
use Stackin\Address;

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
}
