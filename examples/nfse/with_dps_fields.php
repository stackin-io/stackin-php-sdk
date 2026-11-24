<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Stackin\Br\ApproximateTaxes;
use Stackin\Br\NfseDetails;
use Stackin\Br\Product;
use Stackin\DocumentType;
use Stackin\Invoice;

$invoice = new Invoice(apiKey: getenv('STACKIN_API_KEY') ?: null);

$product = new Product(
    description: 'Technical consulting - 10 hours',
    unitPrice: 1500.00,
    serviceCode: '1.06',
    nbs: '115021000',
    internalCode: 'CONS01',
    conditionalDiscount: 10.00,
);

$result = $invoice->issue(
    DocumentType::NFSE,
    'Comprador Teste Ltda',
    '11222333000181',
    [$product],
    nfse: new NfseDetails(
        competenceDate: new DateTimeImmutable(),
        serviceCityCode: '3550308',
        approximateTaxes: new ApproximateTaxes(
            federalPercent: 13.45,
            statePercent: 0.0,
            municipalPercent: 2.0,
        ),
    ),
);

echo json_encode($result['warnings']) . "\n";
