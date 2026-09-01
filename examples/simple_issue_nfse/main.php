<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Stackin\Br\Product;
use Stackin\DocumentType;
use Stackin\Errors\ApiError;
use Stackin\Errors\ConnectionFailedError;
use Stackin\Errors\InvoiceError;
use Stackin\Invoice;

$invoice = new Invoice(apiKey: getenv('STACKIN_API_KEY') ?: null);

try {
    $result = $invoice->issue(
        DocumentType::NFSE,
        'Buyer Company Ltd',
        '11222333000181',
        [
            new Product(
                description: 'Software development',
                amount: 5000.00,
            ),
        ],
    );

    echo "Issued: {$result['access_key']} ({$result['status']})\n";
} catch (InvoiceError $error) {
    echo "Validation error: {$error->getMessage()}\n";
} catch (ApiError $error) {
    echo "API error [{$error->statusCode}]: {$error->detail}\n";
} catch (ConnectionFailedError $error) {
    echo "Connection failed: {$error->getMessage()}\n";
}
