<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Stackin\Br\Product;
use Stackin\DocumentType;
use Stackin\Invoice;

$invoice = new Invoice(apiKey: getenv('STACKIN_API_KEY') ?: null);

$product = new Product(
    description: 'Technical consulting - 10 hours',
    unitPrice: 1500.00,
    serviceCode: '1.06',
);

$result = $invoice->issue(
    DocumentType::NFSE,
    'Comprador Teste Ltda',
    '11222333000181',
    [$product],
);

echo json_encode($result) . "\n";
