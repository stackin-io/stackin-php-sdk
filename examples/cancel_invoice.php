<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Stackin\DocumentType;
use Stackin\Errors\ApiError;
use Stackin\Errors\ConnectionFailedError;
use Stackin\Invoice;

const ACCESS_KEY = '42250611222333000181550010000000011000000017';
const REASON = 'Emitida com dados incorretos do destinatario';

$invoice = new Invoice(apiKey: getenv('STACKIN_API_KEY') ?: null);

try {
    $result = $invoice->cancel(ACCESS_KEY, DocumentType::NFE, REASON);
    echo 'Cancelled: ' . json_encode($result) . "\n";
} catch (ApiError $error) {
    echo "Request rejected ({$error->statusCode}): {$error->detail}\n";
} catch (ConnectionFailedError $error) {
    echo "Could not reach the platform\n";
}
