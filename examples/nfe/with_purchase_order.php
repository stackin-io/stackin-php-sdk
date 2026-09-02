<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Stackin\Br\Product;

issue(
    new Product(
        description: 'Produto vinculado a pedido de compra',
        amount: 75.00,
        purchaseOrder: 'PC-2026-00042',
        purchaseOrderItem: '1',
        ncm: '84433210',
        cfop: '5102',
    ),
    same_state_address(),
);
