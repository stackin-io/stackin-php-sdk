<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Stackin\Br\Product;

issue(
    new Product(
        description: 'Software licensing',
        amount: 1200.00,
        serviceCode: '1.05',
        serviceDiscount: 100.00,
        taxRetained: true,
        observations: 'Licenca anual, renovacao automatica.',
    ),
    tomador_address(),
);
