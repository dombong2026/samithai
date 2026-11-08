<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Support\LocalizationCoverage;

$result = LocalizationCoverage::inspect(dirname(__DIR__, 2));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), "\n";
if (in_array('--strict', $argv, true) && ! $result['structurally_complete']) {
    exit(1);
}
exit(0); // A green structural report never approves native-language translations.
