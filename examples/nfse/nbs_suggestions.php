<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Stackin\FiscalReference;

$reference = new FiscalReference(apiKey: getenv('STACKIN_API_KEY') ?: null);

$found = $reference->nbsSuggestions('1.07');

foreach ($found->suggestions as $row) {
    echo $row->nbs . "\n";
}
