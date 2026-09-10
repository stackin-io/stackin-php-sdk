<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Stackin\Errors\ApiError;
use Stackin\Errors\ConnectionFailedError;
use Stackin\FiscalReference;

$client = new FiscalReference(apiKey: getenv('STACKIN_API_KEY') ?: null);

try {
    $ncm = $client->ncm->get('84716052');
    echo 'NCM: ' . $ncm['description'] . PHP_EOL;
    echo 'Extras: ' . json_encode($ncm['metadata']) . PHP_EOL;

    $found = $client->ncm->search('teclado', limit: 5);
    echo PHP_EOL . $found['total'] . " match 'teclado'; first page:" . PHP_EOL;
    foreach ($found['data'] as $row) {
        echo '  ' . $row['code'] . ' ' . $row['description'] . PHP_EOL;
    }

    echo PHP_EOL . 'Classifications available: '
        . implode(', ', $client->kinds()) . PHP_EOL;

    $cfop = $client->kind('cfop')->get('5102');
    echo 'Any kind by name: ' . $cfop['description'] . PHP_EOL;
} catch (ConnectionFailedError) {
    echo 'Could not reach the platform' . PHP_EOL;
} catch (ApiError $error) {
    echo "Request rejected ({$error->statusCode}): {$error->getMessage()}" . PHP_EOL;
}
