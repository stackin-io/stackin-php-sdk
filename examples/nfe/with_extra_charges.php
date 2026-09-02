<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Stackin\Br\Product;

issue(
    new Product(
        description: 'Produto com encargos adicionais',
        amount: 200.00,
        freight: 15.00,
        insurance: 5.00,
        discount: 10.00,
        otherExpenses: 3.50,
        ncm: '94036000',
        cfop: '5102',
    ),
    same_state_address(),
);
