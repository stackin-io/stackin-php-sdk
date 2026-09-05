<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Stackin\Br\Product;
use Stackin\DocumentType;
use Stackin\Invoice;

$invoice = new Invoice(apiKey: getenv('STACKIN_API_KEY') ?: null);

$product = new Product(
    description: 'Systems analysis and development',
    amount: 2400.00,
    serviceCode: '1.01',
    observations: 'Referente ao contrato #2026-0042, etapa 2 de 3.',
);

$result = $invoice->issue(
    DocumentType::NFSE,
    'Comprador Teste Ltda',
    '11222333000181',
    [$product],
);

echo json_encode($result) . "\n";
