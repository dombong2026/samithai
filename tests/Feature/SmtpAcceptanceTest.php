<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SmtpAcceptanceTest extends TestCase
{
    public function test_smtp_probe_is_denied_when_delivery_is_disabled(): void
    {
        config(['samith.email_delivery_enabled' => false, 'samith.smtp_probe_enabled' => false]);
        $this->assertSame(2, Artisan::call('samith:smtp-acceptance', [
            '--send' => true, '--to' => 'ci@example.test',
        ]));
    }

    public function test_staging_smtp_probe_still_requires_separate_explicit_opt_in(): void
    {
        config([
            'app.env' => 'staging',
            'samith.email_delivery_enabled' => true,
            'samith.smtp_probe_enabled' => false,
            'mail.default' => 'smtp',
            'mail.from.address' => 'operator@samithai.com',
            'mail.mailers.smtp.host' => 'smtp.operator.invalid',
        ]);
        $this->assertSame(2, Artisan::call('samith:smtp-acceptance', [
            '--send' => true, '--to' => 'ci@example.test',
        ]));
    }

    public function test_log_and_array_mailers_never_qualify_for_real_email_acceptance(): void
    {
        foreach (['log', 'array'] as $mailer) {
            config([
                'samith.email_delivery_enabled' => true,
                'mail.default' => $mailer,
                'mail.from.address' => 'operator@samithai.com',
            ]);
            $this->assertSame(2, Artisan::call('samith:smtp-acceptance', [
                '--send' => true, '--to' => 'ci@example.test',
            ]));
        }
    }
}
