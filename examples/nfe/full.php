<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Stackin\Address;
use Stackin\Br\Product;
use Stackin\DocumentType;
use Stackin\Invoice;

$invoice = new Invoice(apiKey: getenv('STACKIN_API_KEY') ?: null);

$product = new Product(
    description: 'Produto completo - todos os campos',
    unitPrice: 999.99,
    unit: 'UN',
    quantity: 2,
    barcode: '7891000100103',
    freight: 20.00,
    insurance: 8.00,
    discount: 15.00,
    otherExpenses: 5.00,
    usedMovableAsset: false,
    purchaseOrder: 'PC-2026-00042',
    purchaseOrderItem: '1',
    ncm: '84713012',
    cfop: '5102',
    cest: '0300700',
    nveCodes: ['NV0001', 'NV0002'],
    indEscala: 'N',
    manufacturerCnpj: '12345678000195',
    taxBenefitCode: 'PR820001',
    presumedCredits: [
        ['code' => 'PR820001', 'percentage' => 3.0, 'amount' => 30.00],
    ],
    exTipi: '01',
    importContentControlNumber: '550E8400-E29B-41D4-A716-446655440000',
    recopiNumber: '00000000000012345678',
    extraGroups: [],
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
