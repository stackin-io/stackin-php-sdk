<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Stackin\Address;
use Stackin\Br\Product;
use Stackin\DocumentType;
use Stackin\Invoice;

$invoice = new Invoice(apiKey: getenv('STACKIN_API_KEY') ?: null);

$product = new Product(
    description: 'Bem movel usado',
    amount: 500.00,
    usedMovableAsset: true,
    ncm: '87032310',
    cfop: '5102',
);

$result = $invoice->issue(
    DocumentType::NFE,
    'Comprador Teste Ltda',
    '11222333000181',
    [$product],
    new Address(
        street: 'Rua das Palmeiras',
        number: '100',
        neighborhood: 'Centro',
        city: 'Florianopolis',
        state: 'SC',
        zipCode: '88010000',
        cityCode: '4205407',
    ),
);

echo json_encode($result) . "\n";
