<?php

use App\Support\InfrastructureReadiness;
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
