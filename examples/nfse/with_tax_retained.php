<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Stackin\Address;
use Stackin\Br\Product;
use Stackin\DocumentType;
use Stackin\Invoice;

$invoice = new Invoice(apiKey: getenv('STACKIN_API_KEY') ?: null);

$product = new Product(
    description: 'UI/UX design',
    amount: 3200.00,
    serviceCode: '1.03',
    taxRetained: true,
);

$result = $invoice->issue(
    DocumentType::NFSE,
    'Comprador Teste Ltda',
    '11222333000181',
    [$product],
    new Address(
        street: 'Rua das Flores',
        number: '123',
        neighborhood: 'Centro',
        city: 'Sao Paulo',
        state: 'SP',
        zipCode: '01310100',
        cityCode: '3550308',
    ),
);

echo json_encode($result) . "\n";
