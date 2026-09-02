<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Stackin\Br\Product;

issue(new Product(
    description: 'Technical consulting - 10 hours',
    amount: 1500.00,
    serviceCode: '1.06',
));
