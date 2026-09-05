<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Stackin\Address;
use Stackin\Br\Product;
use Stackin\DocumentType;
use Stackin\Invoice;

$invoice = new Invoice(apiKey: getenv('STACKIN_API_KEY') ?: null);

$product = new Product(
    description: 'Produto importado',
    amount: 320.00,
    ncm: '85171231',
    cfop: '5102',
    exTipi: '01',
    importContentControlNumber: '550E8400-E29B-41D4-A716-446655440000',
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
