<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Stackin\Br\Product;

issue(
    new Product(
        description: 'Produto de fabricacao em escala',
        amount: 150.00,
        ncm: '87141000',
        cfop: '5102',
        cest: '0100100',
        indEscala: 'N',
        manufacturerCnpj: '12345678000195',
    ),
    same_state_address(),
);
