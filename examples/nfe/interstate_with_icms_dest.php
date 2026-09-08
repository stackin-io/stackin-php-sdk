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
    description: 'Urso de Pelucia Dudu',
    unitPrice: 92.72,
    freight: 9.12,
    ncm: '95030031',
    cfop: '6108',
    tax: (new Tax(
        icms: Tax::icmsSn900(
            orig: '0',
            modBC: '3',
            vBC: '101.84',
            pICMS: '12.0000',
            vICMS: '12.22',
        ),
        icmsUfDest: Tax::icmsUfDest(
            vBCUFDest: '101.84',
            pICMSUFDest: '17.0000',
            pICMSInter: '12.00',
            pICMSInterPart: '100.0000',
            vICMSUFDest: '5.09',
            vICMSUFRemet: '0.00',
        ),
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
