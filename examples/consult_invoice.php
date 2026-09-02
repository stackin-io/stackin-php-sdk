<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Stackin\DocumentType;
use Stackin\Errors\ApiError;
use Stackin\Errors\ConnectionFailedError;
use Stackin\Invoice;

if ($argc < 3) {
    echo "Usage: php examples/consult_invoice.php <access_key> <nfe|nfse>\n";
    exit;
}

$invoice = new Invoice(apiKey: getenv('NFE_TEST_API_KEY') ?: null);

try {
    $result = $invoice->consult($argv[1], DocumentType::from($argv[2]));
    echo 'Status: ' . json_encode($result) . "\n";
} catch (ApiError $error) {
    echo "Request rejected ({$error->statusCode}): {$error->detail}\n";
} catch (ConnectionFailedError $error) {
    echo "Could not reach the platform\n";
}
