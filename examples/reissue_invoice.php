<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Stackin\Errors\ApiError;
use Stackin\Errors\ConnectionFailedError;
use Stackin\Invoice;

if ($argc < 2) {
    echo "Usage: php examples/reissue_invoice.php <invoice_id>\n";
    exit;
}

$invoice = new Invoice(apiKey: getenv('NFE_TEST_API_KEY') ?: null);

try {
    $result = $invoice->reissue($argv[1]);
    echo "Reissued: {$result['access_key']} ({$result['status']})\n";
} catch (ApiError $error) {
    echo "Request rejected ({$error->statusCode}): {$error->detail}\n";
} catch (ConnectionFailedError $error) {
    echo "Could not reach the platform\n";
}
