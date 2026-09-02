<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Stackin\Br\Product;

issue(
    new Product(
        description: 'Caixa de parafusos',
        amount: 12.50,
        unit: 'CX',
        quantity: 20,
        ncm: '73181500',
        cfop: '5102',
    ),
    same_state_address(),
);
