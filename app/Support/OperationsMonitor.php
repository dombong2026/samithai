<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only queue observation. Neither job payloads nor exception text are emitted.
 * A database count is NOT proof that a queue worker is running.
 */
final class OperationsMonitor
{
    public static function inspect(bool $probeDatabase = true): array
    {
        $checks = [];
        $metrics = [
            'Ready queue jobs' => 'not checked',
            'Stale reserved queue jobs' => 'not checked',
            'Failed queue jobs' => 'not checked',
            'Last handled worker probe' => 'not verified',
        ];

        $add = static function (string $id, string $label, string $status, string $detail) use (&$checks): void {
            $checks[$id] = compact('label', 'status', 'detail');
        };

        $driver = (string) config('queue.default');

        if ($driver === 'sync') {
            $add('queue_driver', 'Queue execution mode', 'review',
                'Synchronous jobs execute in web requests. A separate worker is not required, but requests may block.');
        } elseif ($driver === 'database' || $driver === 'redis') {
            $add('queue_driver', 'Queue execution mode', 'pass',
                'A supported asynchronous driver is configured; worker availability is not yet proven.');
        } else {
            $add('queue_driver', 'Queue execution mode', 'review',
                'Review the queue transport and its operational acceptance separately.');
        }

        if (in_array($driver, ['database', 'redis'], true)) {
            $afterCommit = config('queue.connections.'.$driver.'.after_commit') === true;
            $add('after_commit', 'Queue dispatch after database commit', $afterCommit ? 'pass' : 'fail',
                'Jobs must not execute against transaction data before it is committed.');
            $add('worker_evidence', 'Queue worker and retry supervision', 'review',
                'Queue configuration and database counts cannot prove a worker, retry policy or supervisor is operating.');
        }

        if (in_array($driver, ['database', 'redis'], true) && $probeDatabase) {
            try {
                if (Schema::hasTable('worker_probes')) {
                    $recent = DB::table('worker_probes')->whereNotNull('handled_at')->max('handled_at');
                    if ($recent !== null) {
                        $metrics['Last handled worker probe'] = (string) $recent.' UTC';
                    }
                }
            } catch (Throwable) {
                // A failed optional probe query is not a reason to expose SQL errors.
            }
        }

        if ($driver !== 'database' || ! $probeDatabase) {
            $add('queue_tables', 'Database queue inspection', 'review',
                $probeDatabase ? 'Database queue metrics apply only when the database queue transport is active.'
                    : 'Database probe intentionally skipped.');

            return ['checks' => $checks, 'metrics' => $metrics];
        }

        try {
            $connection = config('queue.connections.database.connection') ?: config('database.default');
            $failureConnection = config('queue.failed.database') ?: config('database.default');
            $jobsTable = (string) config('queue.connections.database.table', 'jobs');
            $failedTable = (string) config('queue.failed.table', 'failed_jobs');

            if (! Schema::connection($connection)->hasTable($jobsTable)
                || ! Schema::connection($failureConnection)->hasTable($failedTable)) {
                $add('queue_tables', 'Database queue tables', 'fail',
                    'The jobs or failed_jobs table is missing. Check migrations before enabling workers.');

                return ['checks' => $checks, 'metrics' => $metrics];
            }

            $now = now()->timestamp;
            $retryAfter = max(1, (int) config('queue.connections.database.retry_after', 90));
            $jobs = DB::connection($connection)->table($jobsTable);
            $ready = (clone $jobs)->whereNull('reserved_at')->where('available_at', '<=', $now)->count();
            $stale = (clone $jobs)->whereNotNull('reserved_at')
                ->where('reserved_at', '<=', $now - $retryAfter)->count();
            $failed = DB::connection($failureConnection)->table($failedTable)->count();

            $metrics = [
                'Ready queue jobs' => (string) $ready,
                'Stale reserved queue jobs' => (string) $stale,
                'Failed queue jobs' => (string) $failed,
            ];

            $add('queue_tables', 'Database queue tables', 'pass',
                'Jobs and failed-job tables respond to read-only count queries.');
            $add('queue_backlog', 'Queue backlog and stale reservations',
                $stale > 0 || $ready > 100 ? 'review' : 'pass',
                'Ready jobs: '.$ready.'; reservations beyond retry_after: '.$stale.'.');
            $add('failed_jobs', 'Failed queue jobs', $failed > 0 ? 'review' : 'pass',
                'Failed jobs: '.$failed.'. Review exceptions privately in an approved maintenance session.');
        } catch (Throwable) {
            $add('queue_tables', 'Database queue inspection', 'fail',
                'Unable to read queue tables; no SQL error details are exposed.');
        }

        return ['checks' => $checks, 'metrics' => $metrics];
    }
}
