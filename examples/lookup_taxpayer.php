<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Stackin\Errors\ApiError;
use Stackin\Errors\ConnectionFailedError;
use Stackin\Taxpayer;

$client = new Taxpayer(apiKey: getenv('STACKIN_API_KEY') ?: null);

try {
    $found = $client->get('00000000000191');

    echo 'Name: ' . $found['name'] . PHP_EOL;
    echo 'Trade name: ' . ($found['trade_name'] ?? '-') . PHP_EOL;
    echo 'City code: ' . ($found['city_code'] ?? '-')
        . ' State: ' . ($found['state'] ?? '-') . PHP_EOL;
} catch (ConnectionFailedError) {
    echo 'Could not reach the platform' . PHP_EOL;
} catch (ApiError $error) {
    if ($error->statusCode === 404) {
        echo 'The registry has no record of this tax id yet. It reloads '
            . 'monthly, so a recently registered company is simply not in '
            . 'it — this is not proof the company does not exist, and it '
            . 'is not a validation rule.' . PHP_EOL;
        exit(0);
    }
    echo "Request rejected ({$error->statusCode}): {$error->getMessage()}" . PHP_EOL;
}
