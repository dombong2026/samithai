<?php

namespace App\Support;

/**
 * Checks the completeness of an operator-supplied external release evidence
 * manifest. It cannot independently authenticate reviewers or the evidence.
 */
final class ExternalAcceptance
{
    public const FORMAT = 'samith-ai-external-acceptance/v1';

    public const GATES = [
        'aapanel_clean_install',
        'cpanel_clean_install',
        'prior_release_upgrade',
        'offsite_backup_restore',
        'queue_worker_supervision',
        'smtp_inbox_delivery',
        'payment_provider_and_tax',
        'localization_native_review',
        'legal_counsel_approval',
        'physical_device_acceptance',
        'independent_security_review',
    ];

    public static function evaluate(array $manifest): array
    {
        $errors = [];
        if (($manifest['format'] ?? null) !== self::FORMAT) {
            $errors[] = 'Unsupported acceptance format';
        }

        $sha = $manifest['release_sha'] ?? null;
        if (! is_string($sha) || ! preg_match('/^[a-f0-9]{40}$/D', $sha)) {
            $errors[] = 'Missing or invalid 40-character release commit SHA';
        }

        $packageHash = $manifest['package_sha256'] ?? null;
        if (! is_string($packageHash) || ! preg_match('/^[a-f0-9]{64}$/D', $packageHash)) {
            $errors[] = 'Missing or invalid package SHA-256';
        }

        $gates = $manifest['gates'] ?? null;
        if (! is_array($gates)) {
            $gates = [];
            $errors[] = 'Missing acceptance gates';
        }

        $results = [];
        foreach (self::GATES as $id) {
            $record = $gates[$id] ?? null;
            if (! is_array($record)) {
                $results[$id] = 'missing';
                $errors[] = 'Missing gate: '.$id;
                continue;
            }

            $status = $record['status'] ?? null;
            if (! in_array($status, ['pending', 'failed', 'accepted'], true)) {
                $results[$id] = 'invalid';
                $errors[] = 'Invalid status: '.$id;
                continue;
            }

            if ($status !== 'accepted') {
                $results[$id] = $status;
                continue;
            }

            $reviewer = $record['reviewer'] ?? null;
            $date = $record['reviewed_at'] ?? null;
            $fingerprint = $record['evidence_sha256'] ?? null;
            $reference = $record['evidence_reference'] ?? null;
            $validDate = is_string($date) &&
                preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/D', $date);
            if ($validDate) {
                $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $date, new \DateTimeZone('UTC'));
                $validDate = $parsed !== false && $parsed->format('Y-m-d\TH:i:s\Z') === $date;
            }

            if (! is_string($reviewer) || mb_strlen(trim($reviewer)) < 3 ||
                ! $validDate ||
                ! is_string($fingerprint) || ! preg_match('/^[a-f0-9]{64}$/D', $fingerprint) ||
                ! is_string($reference) || mb_strlen(trim($reference)) < 8) {
                $results[$id] = 'incomplete';
                $errors[] = 'Accepted gate lacks reviewer, UTC time, evidence fingerprint or external reference: '.$id;
                continue;
            }

            $results[$id] = 'accepted';
        }

        foreach (array_keys($gates) as $id) {
            if (! in_array($id, self::GATES, true)) {
                $errors[] = 'Unexpected gate: '.$id;
            }
        }

        $pending = array_filter($results, static fn ($x) => $x !== 'accepted');
        return [
            'ready_for_independent_signoff' => $errors === [] && $pending === [],
            'accepted_count' => count($results) - count($pending),
            'required_count' => count(self::GATES),
            'gate_statuses' => $results,
            'errors' => $errors,
            'notice' => 'Manifest structure checks do not verify evidence authenticity or authorize production deployment.',
        ];
    }
}
