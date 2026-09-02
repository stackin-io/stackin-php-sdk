<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Stackin\Address;
use Stackin\Br\Product;
use Stackin\DocumentType;
use Stackin\Errors\ApiError;
use Stackin\Errors\ConnectionFailedError;
use Stackin\Errors\InvoiceError;
use Stackin\Invoice;

$invoice = new Invoice(apiKey: getenv('NFE_TEST_API_KEY') ?: null);

try {
    $result = $invoice->issue(
        DocumentType::NFE,
        'Comprador Teste Ltda',
        '11222333000181',
        [
            new Product(
                description: 'Rosa Holambra Vermelha',
                amount: 112.44,
                ncm: '06031100',
                cfop: '5102',
            ),
        ],
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

    echo "Issued: {$result['access_key']} ({$result['status']})\n";
} catch (InvoiceError $error) {
    echo "Validation error: {$error->getMessage()}\n";
} catch (ApiError $error) {
    echo "API error [{$error->statusCode}]: {$error->detail}\n";
} catch (ConnectionFailedError $error) {
    echo "Connection failed: {$error->getMessage()}\n";
}
