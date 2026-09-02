<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Stackin\Br\Product;

issue(
    new Product(
        description: 'Rosa Holambra Vermelha',
        amount: 112.44,
        quantity: 6,
        freight: 11.05,
        ncm: '06031100',
        cfop: '6108',
        tax: [
            'icms' => ['ICMSSN102' => ['orig' => '0', 'CSOSN' => '400']],
            'pis' => ['PISNT' => ['CST' => '07']],
            'cofins' => ['COFINSNT' => ['CST' => '07']],
        ],
    ),
    other_state_address(),
);
