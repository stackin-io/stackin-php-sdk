<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Stackin\Br\Product;
use Stackin\DocumentType;
use Stackin\Invoice;

$invoice = new Invoice(apiKey: getenv('STACKIN_API_KEY') ?: null);

$product = new Product(
    description: 'Software development SDK PHP',
    unitPrice: 5000.00,
);

$result = $invoice->issue(
    DocumentType::NFSE,
    'Comprador Teste Ltda',
    '11222333000181',
    [$product],
);

echo json_encode($result) . "\n";
