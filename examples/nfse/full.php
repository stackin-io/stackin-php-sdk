<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Stackin\Address;
use Stackin\Br\Product;
use Stackin\DocumentType;
use Stackin\Invoice;

$invoice = new Invoice(apiKey: getenv('STACKIN_API_KEY') ?: null);

$product = new Product(
    description: 'Software licensing',
    amount: 1200.00,
    serviceCode: '1.05',
    serviceDiscount: 100.00,
    taxRetained: true,
    observations: 'Licenca anual, renovacao automatica.',
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
