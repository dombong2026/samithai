<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * A read-only, operator-triggered restoration comparison.
 * It fingerprints row IDs and validates private media checksums, not full row contents.
 * Always restore into a separate database and storage location to test real recovery.
 */
final class RecoveryInventory
{
    public const FORMAT = 'samith-recovery-inventory/v1';

    private const TABLES = [
        'users', 'categories', 'prompts', 'orders', 'order_items',
        'prompt_entitlements', 'legal_versions', 'legal_acceptances',
        'media_assets', 'content_entries', 'audit_events',
        'payment_transactions', 'migrations',
    ];

    public static function capture(): array
    {
        $tables = [];

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                $tables[$table] = ['present' => false, 'count' => null, 'id_sha256' => null];
                continue;
            }

            $hash = hash_init('sha256');
            $count = 0;
            DB::table($table)->select('id')->chunkById(500, static function ($rows) use (&$count, $hash): void {
                foreach ($rows as $row) {
                    hash_update($hash, (string) $row->id."\n");
                    $count++;
                }
            });

            $tables[$table] = [
                'present' => true,
                'count' => $count,
                'id_sha256' => hash_final($hash),
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
            foreach (['present', 'count', 'id_sha256'] as $key) {
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
