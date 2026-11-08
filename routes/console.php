<?php

use App\Support\InfrastructureReadiness;
use App\Support\RecoveryInventory;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('samith:infrastructure {--json : Emit a credential-free JSON report} {--strict : Fail on any critical runtime failure}', function () {
    $report = InfrastructureReadiness::inspect();

    if ($this->option('json')) {
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    } else {
        foreach ($report['checks'] as $check) {
            $this->line(strtoupper($check['status']).'  '.$check['label'].' — '.$check['detail']);
        }
        $this->line('Summary: '.json_encode($report['summary']));
        $this->warn('This is not a customer hosting, backup restoration, payment, email delivery or legal acceptance certificate.');
    }

    return $this->option('strict') && $report['summary']['fail'] > 0 ? 1 : 0;
})->purpose('Read-only Laravel infrastructure readiness diagnostics without printing secrets');


Artisan::command('samith:recovery-inventory {--json : Print the inventory as JSON} {--compare= : Compare with a prior exported inventory file} {--strict : Fail on missing tables or media mismatches}', function () {
    try {
        $inventory = RecoveryInventory::capture();
        $differences = [];
        if ($file = $this->option('compare')) {
            if (! is_file($file) || ! is_readable($file)) {
                $this->error('Baseline inventory is not a readable file.');
                return 1;
            }
            $baseline = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($baseline)) {
                $this->error('Baseline inventory must be a JSON object.');
                return 1;
            }
            $differences = RecoveryInventory::differences($baseline, $inventory);
        }

        if ($this->option('json')) {
            $this->line(json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            foreach ($inventory['tables'] as $name => $table) {
                $this->line($name.': '.($table['present'] ? (string) $table['count'] : 'MISSING'));
            }
            $this->line('Media: '.json_encode($inventory['media']));
        }

        if ($this->option('compare')) {
            if ($differences === []) {
                $this->info('RECOVERY_INVENTORY_MATCH');
            } else {
                $this->error('RECOVERY_INVENTORY_DIFFERENCES: '.implode(', ', $differences));
            }
        }

        if ($differences !== [] || ($this->option('strict') && ! RecoveryInventory::healthy($inventory))) {
            return 1;
        }

        return 0;
    } catch (\Throwable) {
        $this->error('Recovery inventory failed. Check database, storage or baseline format privately.');
        return 1;
    }
})->purpose('Read-only row-ID and private-media recovery inventory; compare against a restored staging clone');


Artisan::command('samith:queue-probe {--dispatch : Enqueue a one-time probe} {--check= : Check the dispatched UUID}', function () {
    if (! in_array(config('queue.default'), ['database', 'redis'], true)) {
        $this->error('This check requires an asynchronous database or redis queue. Sync mode is not worker proof.');
        return 2;
    }
    if (! \Illuminate\Support\Facades\Schema::hasTable('worker_probes')) {
        $this->error('Worker probe table missing; apply migrations on an approved staging host.');
        return 2;
    }

    $check = $this->option('check');
    if ($check && $this->option('dispatch')) {
        $this->error('Use --check or --dispatch, not both.');
        return 2;
    }

    if ($check) {
        if (! \Illuminate\Support\Str::isUuid((string) $check)) {
            $this->error('Invalid probe ID.');
            return 2;
        }
        $probe = \Illuminate\Support\Facades\DB::table('worker_probes')->where('id', $check)->first();
        if (! $probe || $probe->handled_at === null) {
            $this->warn('WORKER_PROBE_NOT_HANDLED');
            return 1;
        }
        if (\Illuminate\Support\Carbon::parse($probe->handled_at)->lt(now()->subMinutes(15))) {
            $this->warn('WORKER_PROBE_STALE');
            return 1;
        }
        $this->info('WORKER_PROBE_HANDLED: '.$check);
        $this->warn('One handled job does not prove a running supervisor or restart recovery.');
        return 0;
    }

    if ($this->option('dispatch')) {
        $id = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('worker_probes')->insert([
            'id' => $id, 'queued_at' => now(), 'handled_at' => null,
        ]);
        try {
            \App\Jobs\WorkerProbeJob::dispatch($id);
        } catch (\Throwable) {
            $this->error('Queue dispatch failed; check transport configuration privately.');
            return 1;
        }
        $this->line('PROBE_ID='.$id);
        $this->warn('Run a supervised worker, then check the ID. Dispatch alone is not acceptance.');
        return 0;
    }

    $this->error('Use --dispatch or --check=UUID.');
    return 2;
})->purpose('Explicit operator-only async worker proof, without private job payloads');
