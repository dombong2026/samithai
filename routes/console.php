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
