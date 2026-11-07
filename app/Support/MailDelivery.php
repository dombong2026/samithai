<?php

namespace App\Support;

final class MailDelivery
{
    /**
     * A configuration safety gate, not a live SMTP or inbox acceptance check.
     * Never emit recovery or verification tokens into Laravel's log/array mailer.
     */
    public static function permitted(): bool
    {
        if (! config('samith.email_delivery_enabled')) return false;

        $mailer = (string) config('mail.default');
        if (! in_array($mailer, ['smtp','ses','postmark','resend','mailgun'], true)) return false;

        $from = (string) config('mail.from.address');
        if (! filter_var($from, FILTER_VALIDATE_EMAIL)
            || str_ends_with(strtolower($from), '@example.com')
            || str_ends_with(strtolower($from), '@example.test')) return false;

        if ($mailer === 'smtp') {
            $host = (string) config('mail.mailers.smtp.host');
            if ($host === '' || $host === '127.0.0.1' || $host === 'localhost') return false;
        }

        return true;
    }
}
