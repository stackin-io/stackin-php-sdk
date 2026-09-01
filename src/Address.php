<?php

declare(strict_types=1);

namespace Stackin;

/**
 * The buyer's address. Only `state` is currently read by the API —
 * used to resolve idDest (interstate vs internal) on NF-e — but the
 * wider shape is kept since more fields are expected to be read
 * later.
 */
final class Address
{
    public function __construct(
        public readonly ?string $state = null,
        public readonly ?string $street = null,
        public readonly ?string $number = null,
        public readonly ?string $neighborhood = null,
        public readonly ?string $city = null,
        public readonly ?string $zipCode = null,
    ) {
    }
}
