<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Stackin\Br\Product;

issue(
    new Product(
        description: 'Produto com codigo de barras',
        amount: 29.90,
        barcode: '7891000100103',
        ncm: '21069090',
        cfop: '5102',
    ),
    same_state_address(),
);
