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

$tomadorAddress = new Address(
    street: 'Rua das Flores',
    number: '123',
    neighborhood: 'Centro',
    city: 'Sao Paulo',
    state: 'SP',
    zipCode: '01310100',
    cityCode: '3550308',
);

$products = [
    new Product(description: 'Software development', amount: 5000.00),
    new Product(
        description: 'Technical consulting - 10 hours',
        amount: 1500.00,
        serviceCode: '1.06',
    ),
    new Product(
        description: 'Monthly support and maintenance',
        amount: 800.00,
        serviceCode: '1.07',
        serviceDiscount: 50.00,
    ),
    new Product(
        description: 'UI/UX design',
        amount: 3200.00,
        serviceCode: '1.03',
        taxRetained: true,
    ),
    new Product(
        description: 'Systems analysis and development',
        amount: 2400.00,
        serviceCode: '1.01',
        observations: 'Referente ao contrato #2026-0042, etapa 2 de 3.',
    ),
];

foreach ($products as $product) {
    try {
        $result = $invoice->issue(
            DocumentType::NFSE,
            'Comprador Teste Ltda',
            '11222333000181',
            [$product],
            $product->taxRetained ? $tomadorAddress : null,
        );

        echo "Issued: {$result['access_key']} ({$result['status']})\n";
    } catch (InvoiceError $error) {
        echo "Validation error: {$error->getMessage()}\n";
    } catch (ApiError $error) {
        echo "API error [{$error->statusCode}]: {$error->detail}\n";
    } catch (ConnectionFailedError $error) {
        echo "Connection failed: {$error->getMessage()}\n";
    }
}
