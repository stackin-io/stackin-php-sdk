<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Stackin\Br\Product;

issue(new Product(
    description: 'Monthly support and maintenance',
    amount: 800.00,
    serviceCode: '1.07',
    serviceDiscount: 50.00,
));
