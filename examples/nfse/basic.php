<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Stackin\Br\Product;

issue(new Product(description: 'Software development', amount: 5000.00));
