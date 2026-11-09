<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * A read-only, operator-triggered restoration comparison.
 * It fingerprints row IDs and selected immutable/critical fields using an HMAC,
 * plus validates private media checksums. This is not a whole-database backup check.
 * Always restore into a separate database and storage location to test real recovery.
 */
final class RecoveryInventory
{
    public const FORMAT = 'samith-recovery-inventory/v4';

    private const TABLES = [
        'users', 'categories', 'prompts', 'prompt_reviews', 'prompt_saves', 'orders', 'order_items',
        'prompt_entitlements', 'legal_versions', 'legal_acceptances',
        'media_assets', 'content_entries', 'audit_events',
        'payment_transactions', 'migrations',
    ];


    private const FINGERPRINT_COLUMNS = [
        'users' => ['id', 'name', 'email', 'role'],
        'categories' => ['id', 'name', 'slug', 'is_active'],
        'prompts' => ['id', 'user_id', 'category_id', 'title', 'content', 'price', 'status', 'license_type'],
        'prompt_reviews' => ['id', 'prompt_id', 'actor_id', 'action', 'note', 'created_at'],
        'prompt_saves' => ['id', 'user_id', 'prompt_id', 'created_at'],
        'orders' => ['id', 'user_id', 'number', 'status', 'currency', 'total'],
        'order_items' => ['id', 'order_id', 'prompt_id', 'content_snapshot', 'unit_price', 'license_type'],
        'prompt_entitlements' => ['id', 'user_id', 'prompt_id', 'order_id', 'content_snapshot', 'license_type'],
        'legal_versions' => ['id', 'kind', 'version', 'body_html', 'status', 'review_reference'],
        'legal_acceptances' => ['id', 'user_id', 'order_id', 'legal_version_id', 'accepted_at'],
        'media_assets' => ['id', 'user_id', 'kind', 'file_path', 'checksum', 'bytes'],
        'content_entries' => ['id', 'user_id', 'kind', 'slug', 'body_html', 'status'],
        'audit_events' => ['id', 'user_id', 'action', 'subject_type', 'subject_id', 'details', 'created_at'],
        'payment_transactions' => ['id', 'order_id', 'provider', 'provider_reference', 'status', 'currency', 'amount'],
        'migrations' => ['id', 'migration', 'batch'],
    ];

    public static function capture(): array
    {
        $tables = [];

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                $tables[$table] = ['present' => false, 'count' => null, 'id_sha256' => null, 'content_hmac_sha256' => null];
                continue;
            }

            $idHash = hash_init('sha256');
            // The site encryption key is also required to validate a restored
            // instance. Keyed digests do not reveal plaintext personal/licensed data.
            $contentHash = hash_init('sha256', HASH_HMAC, (string) config('app.key'));
            $columns = self::FINGERPRINT_COLUMNS[$table];
            $count = 0;

            DB::table($table)->select($columns)->chunkById(500,
                static function ($rows) use (&$count, $idHash, $contentHash, $columns): void {
                    foreach ($rows as $row) {
                        hash_update($idHash, (string) $row->id."\n");
                        foreach ($columns as $column) {
                            $value = $row->{$column};
                            if ($value === null) {
                                hash_update($contentHash, 'N;');
                            } else {
                                $value = (string) $value;
                                hash_update($contentHash, 'S'.strlen($value).':'.$value.';');
                            }
                        }
                        hash_update($contentHash, "\n");
                        $count++;
                    }
                }
            );

            $tables[$table] = [
                'present' => true,
                'count' => $count,
                'id_sha256' => hash_final($idHash),
                'content_hmac_sha256' => hash_final($contentHash),
            ];
        }

        $media = ['referenced' => 0, 'missing' => 0, 'checksum_mismatch' => 0];

        if ($tables['media_assets']['present']) {
            $disk = Storage::disk('local');
            foreach (DB::table('media_assets')->whereNotNull('file_path')
                ->select('file_path', 'checksum')->cursor() as $asset) {
                $media['referenced']++;
                $path = (string) $asset->file_path;

                if ($path === '' || ! $disk->exists($path)) {
                    $media['missing']++;
                    continue;
                }

                if ($asset->checksum !== null && $asset->checksum !== '') {
                    $stream = $disk->readStream($path);
                    if (! is_resource($stream)) {
                        $media['missing']++;
                        continue;
                    }

                    try {
                        $ctx = hash_init('sha256');
                        hash_update_stream($ctx, $stream);
                        $actual = hash_final($ctx);
                    } finally {
                        fclose($stream);
                    }

                    if (! hash_equals(strtolower((string) $asset->checksum), $actual)) {
                        $media['checksum_mismatch']++;
                    }
                }
            }
        }

        return [
            'format' => self::FORMAT,
            'database_driver' => (string) DB::getDriverName(),
            'tables' => $tables,
            'media' => $media,
        ];
    }

    /**
     * Return field identifiers only; never emit customer, license or media data.
     */
    public static function differences(array $baseline, array $restored): array
    {
        if (($baseline['format'] ?? null) !== self::FORMAT
            || ($restored['format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException('Unsupported recovery inventory format.');
        }

        $differences = [];
        if (($baseline['database_driver'] ?? null) !== ($restored['database_driver'] ?? null)) {
            $differences[] = 'database_driver';
        }

        foreach (self::TABLES as $table) {
            foreach (['present', 'count', 'id_sha256', 'content_hmac_sha256'] as $key) {
                if (($baseline['tables'][$table][$key] ?? null) !== ($restored['tables'][$table][$key] ?? null)) {
                    $differences[] = $table.'.'.$key;
                }
            }
        }

        foreach (['referenced', 'missing', 'checksum_mismatch'] as $key) {
            if (($baseline['media'][$key] ?? null) !== ($restored['media'][$key] ?? null)) {
                $differences[] = 'media.'.$key;
            }
        }

        return $differences;
    }

    public static function healthy(array $inventory): bool
    {
        foreach ($inventory['tables'] ?? [] as $table) {
            if (! ($table['present'] ?? false)) {
                return false;
            }
        }

        return count($inventory['tables'] ?? []) === count(self::TABLES)
            && ($inventory['media']['missing'] ?? -1) === 0
            && ($inventory['media']['checksum_mismatch'] ?? -1) === 0;
    }
}
