<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Stackin\Br\Product;

issue(
    new Product(
        description: 'Produto completo - todos os campos',
        amount: 999.99,
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
    ),
    same_state_address(),
);
