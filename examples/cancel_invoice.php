<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Stackin\DocumentType;
use Stackin\Errors\ApiError;
use Stackin\Errors\ConnectionFailedError;
use Stackin\Invoice;

if ($argc < 4) {
    echo "Usage: php examples/cancel_invoice.php <access_key> <nfe|nfse> <reason>\n";
    exit;
}

$invoice = new Invoice(apiKey: getenv('NFE_TEST_API_KEY') ?: null);

try {
    $result = $invoice->cancel($argv[1], DocumentType::from($argv[2]), $argv[3]);
    echo 'Cancelled: ' . json_encode($result) . "\n";
} catch (ApiError $error) {
    echo "Request rejected ({$error->statusCode}): {$error->detail}\n";
} catch (ConnectionFailedError $error) {
    echo "Could not reach the platform\n";
}
