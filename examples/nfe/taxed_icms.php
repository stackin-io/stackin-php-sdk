<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Stackin\Address;
use Stackin\Br\Product;
use Stackin\DocumentType;
use Stackin\Invoice;

$invoice = new Invoice(apiKey: getenv('STACKIN_API_KEY') ?: null);

$product = new Product(
    description: 'Plastico celofane 50x50',
    amount: 0.27,
    freight: 0.03,
    ncm: '39202019',
    cfop: '6108',
    tax: [
        'icms' => ['ICMSSN102' => ['orig' => '0', 'CSOSN' => '102']],
        'pis' => ['PISAliq' => [
            'CST' => '01',
            'vBC' => '0.30',
            'pPIS' => '0.6500',
            'vPIS' => '0.00',
        ]],
        'cofins' => ['COFINSAliq' => [
            'CST' => '01',
            'vBC' => '0.30',
            'pCOFINS' => '3.0000',
            'vCOFINS' => '0.01',
        ]],
    ],
);

$result = $invoice->issue(
    DocumentType::NFE,
    'Comprador Teste Ltda',
    '11222333000181',
    [$product],
    new Address(
        street: 'Avenida Atlantica',
        number: '500',
        neighborhood: 'Copacabana',
        city: 'Rio de Janeiro',
        state: 'RJ',
        zipCode: '22010000',
        cityCode: '3304557',
    ),
);

echo json_encode($result) . "\n";
