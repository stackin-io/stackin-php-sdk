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

function same_state_address(): Address
{
    return new Address(
        street: 'Rua das Palmeiras',
        number: '100',
        neighborhood: 'Centro',
        city: 'Florianopolis',
        state: 'SC',
        zipCode: '88010000',
        cityCode: '4205407',
    );
}

function other_state_address(): Address
{
    return new Address(
        street: 'Avenida Atlantica',
        number: '500',
        neighborhood: 'Copacabana',
        city: 'Rio de Janeiro',
        state: 'RJ',
        zipCode: '22010000',
        cityCode: '3304557',
    );
}

function issue(Product $product, Address $recipientAddress): void
{
    $invoice = new Invoice(apiKey: getenv('NFE_TEST_API_KEY') ?: null);

    try {
        $result = $invoice->issue(
            DocumentType::NFE,
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
