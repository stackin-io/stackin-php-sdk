<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Stackin\Br\Product;

issue(
    new Product(
        description: 'Plastico celofane 50x50',
        amount: 0.27,
        freight: 0.03,
        ncm: '39202019',
        cfop: '6108',
        tax: [
            'icms' => ['ICMSSN102' => ['orig' => '0', 'CSOSN' => '102']],
            'pis' => ['PISAliq' => ['CST' => '01', 'vBC' => '0.30', 'pPIS' => '0.6500', 'vPIS' => '0.00']],
            'cofins' => ['COFINSAliq' => ['CST' => '01', 'vBC' => '0.30', 'pCOFINS' => '3.0000', 'vCOFINS' => '0.01']],
        ],
    ),
    other_state_address(),
);
