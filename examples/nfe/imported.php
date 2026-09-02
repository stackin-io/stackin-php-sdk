<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Stackin\Br\Product;

issue(
    new Product(
        description: 'Produto importado',
        amount: 320.00,
        ncm: '85171231',
        cfop: '5102',
        exTipi: '01',
        importContentControlNumber: '550E8400-E29B-41D4-A716-446655440000',
    ),
    same_state_address(),
);
