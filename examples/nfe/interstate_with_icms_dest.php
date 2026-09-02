<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Stackin\Br\Product;

issue(
    new Product(
        description: 'Urso de Pelucia Dudu',
        amount: 92.72,
        freight: 9.12,
        ncm: '95030031',
        cfop: '6108',
        tax: [
            'icms' => ['ICMSSN900' => [
                'orig' => '0', 'CSOSN' => '900', 'modBC' => '3',
                'vBC' => '101.84', 'pICMS' => '12.0000', 'vICMS' => '12.22',
            ]],
            'icms_uf_dest' => [
                'vBCUFDest' => '101.84', 'pICMSUFDest' => '17.0000',
                'pICMSInter' => '12.00', 'pICMSInterPart' => '100.0000',
                'vICMSUFDest' => '5.09', 'vICMSUFRemet' => '0.00',
            ],
            'pis' => ['PISNT' => ['CST' => '07']],
            'cofins' => ['COFINSNT' => ['CST' => '07']],
        ],
    ),
    other_state_address(),
);
