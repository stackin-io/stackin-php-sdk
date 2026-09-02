<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Stackin\Br\Product;

issue(
    new Product(
        description: 'UI/UX design',
        amount: 3200.00,
        serviceCode: '1.03',
        taxRetained: true,
    ),
    tomador_address(),
);
