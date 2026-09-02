<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Stackin\Br\Product;

issue(
    new Product(
        description: 'Produto basico',
        amount: 50.00,
        ncm: '84713012',
        cfop: '5102',
    ),
    same_state_address(),
);
