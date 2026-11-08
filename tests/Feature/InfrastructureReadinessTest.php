<?php

namespace Tests\Feature;

use App\Support\InfrastructureReadiness;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InfrastructureReadinessTest extends TestCase
{
    public function test_externally_unverified_integrations_remain_visible_as_review_items(): void
    {
        config()->set('samith.email_delivery_enabled', false);
        config()->set('payments.enabled', false);

        $report = InfrastructureReadiness::inspect(false);

        $this->assertSame('review', $report['checks']['email']['status']);
        $this->assertSame('pass', $report['checks']['payments']['status']);
        $this->assertSame('review', $report['checks']['database']['status']);
        $this->assertSame(
            count($report['checks']),
            array_sum($report['summary'])
        );
    }

    public function test_unsafe_production_flags_are_reported_as_failures(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.debug', true);
        config()->set('app.url', 'http://samithai.test');
        config()->set('session.secure', false);
        config()->set('payments.enabled', true);

        $report = InfrastructureReadiness::inspect(false);

        foreach (['debug', 'https_url', 'secure_cookie', 'payments'] as $key) {
            $this->assertSame('fail', $report['checks'][$key]['status'], $key);
        }
        $this->assertGreaterThanOrEqual(4, $report['summary']['fail']);
    }

    public function test_cli_json_report_does_not_leak_database_credentials(): void
    {
        config()->set('database.connections.sqlite.password', 'SECRET_DO_NOT_PRINT_123');
        $status = Artisan::call('samith:infrastructure', ['--json' => true]);
        $output = Artisan::output();
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $status);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('checks', $report);
        $this->assertStringNotContainsString('SECRET_DO_NOT_PRINT_123', $output);
    }

    public function test_cli_strict_mode_rejects_unsafe_production_configuration(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.debug', true);

        $this->assertSame(1, Artisan::call('samith:infrastructure', ['--strict' => true]));
    }
}
