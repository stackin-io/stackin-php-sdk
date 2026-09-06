<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Stackin\Address;
use Stackin\Br\Product;
use Stackin\Br\Tax;
use Stackin\DocumentType;
use Stackin\Invoice;

$invoice = new Invoice(apiKey: getenv('STACKIN_API_KEY') ?: null);

$product = new Product(
    description: 'Rosa Holambra Vermelha',
    amount: 112.44,
    quantity: 6,
    freight: 11.05,
    ncm: '06031100',
    cfop: '6108',
    tax: (new Tax(
        icms: Tax::icmsSn102(CSOSN: '400', orig: '0'),
        pis: Tax::pisNt('07'),
        cofins: Tax::cofinsNt('07'),
    ))->toArray(),
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
