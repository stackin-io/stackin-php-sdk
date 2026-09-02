<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Stackin\Br\Product;

issue(
    new Product(
        description: 'Bem movel usado',
        amount: 500.00,
        usedMovableAsset: true,
        ncm: '87032310',
        cfop: '5102',
    ),
    same_state_address(),
);
