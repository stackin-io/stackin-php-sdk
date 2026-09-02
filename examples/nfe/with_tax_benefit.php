<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Stackin\Br\Product;

issue(
    new Product(
        description: 'Produto com beneficio fiscal',
        amount: 80.00,
        ncm: '22021000',
        cfop: '5102',
        cest: '0300700',
        taxBenefitCode: 'PR820001',
        presumedCredits: [
            ['code' => 'PR820001', 'percentage' => 3.0, 'amount' => 2.40],
        ],
    ),
    same_state_address(),
);
