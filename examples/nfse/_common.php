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

function tomador_address(): Address
{
    return new Address(
        street: 'Rua das Flores',
        number: '123',
        neighborhood: 'Centro',
        city: 'Sao Paulo',
        state: 'SP',
        zipCode: '01310100',
        cityCode: '3550308',
    );
}

function issue(Product $product, ?Address $recipientAddress = null): void
{
    $invoice = new Invoice(apiKey: getenv('NFE_TEST_API_KEY') ?: null);

    try {
        $result = $invoice->issue(
            DocumentType::NFSE,
            'Comprador Teste Ltda',
            '11222333000181',
            [$product],
            $recipientAddress,
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
