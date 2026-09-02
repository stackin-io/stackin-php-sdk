<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Stackin\Br\Product;

issue(new Product(
    description: 'Systems analysis and development',
    amount: 2400.00,
    serviceCode: '1.01',
    observations: 'Referente ao contrato #2026-0042, etapa 2 de 3.',
));
