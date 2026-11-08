<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Support\ExternalAcceptance;

$path = $argv[1] ?? null;
$expectBlocked = in_array('--expect-blocked', $argv, true);
if (! is_string($path) || $path === '--expect-blocked' || ! is_file($path) || ! is_readable($path)) {
    fwrite(STDERR, "Usage: php support/acceptance/check.php /absolute/or/relative/manifest.json [--expect-blocked]\n");
    exit(2);
}

try {
    $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($manifest)) {
        throw new RuntimeException('Expected an object.');
    }

    $result = ExternalAcceptance::evaluate($manifest);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";

    // For repository CI: the tracked unapproved template MUST remain blocked.
    if ($expectBlocked) {
        exit(! $result['ready_for_independent_signoff'] && $result['accepted_count'] === 0 ? 0 : 1);
    }

    exit($result['ready_for_independent_signoff'] ? 0 : 1);
} catch (Throwable) {
    fwrite(STDERR, "Acceptance manifest is unreadable or invalid; no release approval was granted.\n");
    exit(2);
}
