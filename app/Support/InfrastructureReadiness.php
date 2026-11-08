<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read-only, credential-free runtime diagnostics.
 *
 * These checks are signals, not approval of a production release.
 * External email, billing, backups and actual hosting acceptance remain manual gates.
 */
final class InfrastructureReadiness
{
    public static function inspect(bool $probeDatabase = true): array
    {
        $checks = [];
        $add = static function (string $id, string $label, string $status, string $detail) use (&$checks): void {
            $checks[$id] = compact('label', 'status', 'detail');
        };

        $production = config('app.env') === 'production';
        $appUrl = (string) config('app.url');
        $key = (string) config('app.key');
        $rawKey = str_starts_with($key, 'base64:')
            ? base64_decode(substr($key, 7), true)
            : $key;

        $add('php', 'PHP 8.3 or newer', PHP_VERSION_ID >= 80300 ? 'pass' : 'fail', 'Laravel 13 runtime requirement.');
        foreach (['openssl', 'pdo_mysql', 'mbstring', 'fileinfo', 'xml', 'dom'] as $extension) {
            $add('extension_'.$extension, 'PHP extension: '.$extension, extension_loaded($extension) ? 'pass' : 'fail', 'Required by installation, security or content handling.');
        }

        $add('app_key', 'Application encryption key',
            is_string($rawKey) && strlen($rawKey) === 32 ? 'pass' : 'fail',
            'Requires a valid 256-bit application key; its value is never displayed.');
        $add('debug', 'Production debug mode',
            ! $production || config('app.debug') === false ? 'pass' : 'fail',
            'APP_DEBUG must be false on production hosts.');
        $add('https_url', 'Production canonical HTTPS URL',
            ! $production || str_starts_with(strtolower($appUrl), 'https://') ? 'pass' : 'fail',
            'Configure a valid HTTPS APP_URL on the production host.');
        $add('secure_cookie', 'Production session cookie security',
            ! $production || (config('session.secure') === true && config('session.http_only') === true) ? 'pass' : 'fail',
            'Production session cookies must be Secure and HttpOnly.');

        $add('storage', 'Writable private application storage',
            is_dir(storage_path('app/private')) && is_writable(storage_path('app/private'))
                && is_writable(storage_path('framework')) ? 'pass' : 'fail',
            'Private application files and runtime storage must be writable.');
        $add('bootstrap_cache', 'Writable Laravel bootstrap cache',
            is_dir(base_path('bootstrap/cache')) && is_writable(base_path('bootstrap/cache')) ? 'pass' : 'fail',
            'Needed for safe configuration and route compilation.');

        $publicPath = realpath(public_path());
        $privatePath = realpath(storage_path('app/private'));
        $privateOutsideWebroot = $publicPath !== false && $privatePath !== false
            && $privatePath !== $publicPath
            && ! str_starts_with($privatePath, rtrim($publicPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
        $add('private_media', 'Private media outside the public webroot',
            config('filesystems.disks.local.driver') === 'local'
                && $privateOutsideWebroot ? 'pass' : 'fail',
            'Uploaded files must never be stored directly under public/.');

        $exposures = ['.env', 'vendor/autoload.php', 'storage/app/installed.lock', '.git/config'];
        $exposed = array_filter($exposures, static fn (string $p): bool => file_exists(public_path($p)));
        $add('webroot', 'Sensitive files absent from public/',
            count($exposed) === 0 ? 'pass' : 'fail',
            'Only public/ should be reachable from the document root; check HTTP exposure separately.');

        if ($probeDatabase) {
            try {
                DB::select('SELECT 1');
                $add('database', 'Database responds to a read-only query', 'pass', 'A SELECT 1 query succeeded; no migrations were run.');
            } catch (Throwable) {
                $add('database', 'Database responds to a read-only query', 'fail',
                    'Database unavailable; no credentials or exception details are exposed.');
            }
        } else {
            $add('database', 'Database responds to a read-only query', 'review',
                'Database probe intentionally skipped.');
        }

        $add('install_lock', 'Installation complete',
            is_file(storage_path('app/installed.lock')) ? 'pass' : 'review',
            'First-time installation must finish before production acceptance.');
        $add('queue', 'Supported queue transport',
            in_array(config('queue.default'), ['sync', 'database', 'redis'], true) ? 'pass' : 'review',
            'Sync works without workers but executes tasks during web requests.');
        $add('payments', 'Unverified real-money payment gate disabled',
            config('payments.enabled') === false ? 'pass' : 'fail',
            'No authorized, audited payment provider adapter is present.');

        if (config('samith.email_delivery_enabled')) {
            $add('email', 'Outbound email readiness',
                MailDelivery::permitted() ? 'review' : 'fail',
                'Configured delivery is not proof of actual SMTP, DNS or inbox acceptance.');
        } else {
            $add('email', 'Outbound email readiness', 'review',
                'Email is intentionally disabled until transport and inbox delivery are verified.');
        }

        $operations = OperationsMonitor::inspect($probeDatabase);
        foreach ($operations['checks'] as $id => $check) {
            $add('ops_'.$id, $check['label'], $check['status'], $check['detail']);
        }

        $add('backup_restore_drill', 'Independent backup restoration', 'review',
            'A read-only inventory is available, but a separately restored host and media copy must be accepted by an operator.');
        $add('upgrade_rollback_drill', 'Independent upgrade rollback', 'review',
            'No production upgrade or rollback is authorized from this dashboard.');

        $add('dependency_locks', 'Reproducible Composer and npm dependencies',
            is_file(base_path('composer.lock')) && is_file(base_path('package-lock.json')) ? 'pass' : 'review',
            'Commit generated lockfiles before approving an independently repeatable release.');

        $summary = ['pass' => 0, 'review' => 0, 'fail' => 0];
        foreach ($checks as $check) {
            $summary[$check['status']]++;
        }

        return [
            'checks' => $checks,
            'summary' => $summary,
            'runtime' => [
                'Laravel version' => app()->version(),
                'PHP version' => PHP_VERSION,
                'Environment' => (string) config('app.env'),
                'Queue mode' => (string) config('queue.default'),
                'Cache driver' => (string) config('cache.default'),
                'Disk' => (string) config('filesystems.default'),
                ...$operations['metrics'],
            ],
        ];
    }
}
