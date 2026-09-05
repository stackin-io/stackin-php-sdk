<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Stackin\Errors\ApiError;
use Stackin\Errors\ConnectionFailedError;
use Stackin\Invoice;

const INVOICE_ID = '00000000-0000-0000-0000-000000000000';

$invoice = new Invoice(apiKey: getenv('STACKIN_API_KEY') ?: null);

try {
    $result = $invoice->reissue(INVOICE_ID);
    echo "Reissued: {$result['access_key']} ({$result['status']})\n";
} catch (ApiError $error) {
    echo "Request rejected ({$error->statusCode}): {$error->detail}\n";
} catch (ConnectionFailedError $error) {
    echo "Could not reach the platform\n";
}
