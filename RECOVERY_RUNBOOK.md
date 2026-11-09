# Samith AI — Backup, recovery, upgrade and queue operations

This is an operator runbook, not authorization to change a production host.
No automatic recovery, production backup task, deployment, payment adapter or queue supervisor is installed by these source changes.

## Release boundaries

Production is samithai.com; do not run these drills on its live database or storage.
GitHub CI uses fake users, licensed content and disposable MySQL 8 databases only.
Keep SAMITH_PAYMENTS_ENABLED=false; external payment, SMTP, legal, translation and physical device gates remain open.

## Before an operator-approved upgrade

1. Identify the exact release commit, artifact hash, PHP runtime, MySQL version, active webroot and app/storage paths. Verify no unaudited code exists.
2. Schedule the maintenance window, log who approved it and define rollback criteria. Never edit the running application until backups and a staged restore pass.
3. Preserve a consistent MySQL snapshot with a verified MySQL dump tool, the entire private storage tree (including licensed media), uploads and an encrypted copy of the site's .env and APP_KEY.
4. Write SHA-256 manifest files in a restricted, off-webroot directory. Encrypt backups at rest, transfer an off-host copy, enforce retention and test that each file is readable.
5. On the source (where safely permitted), export the read-only recovery inventory:

       php artisan samith:recovery-inventory --json --strict > /secure/private/samith-before.json

   The file exposes only counts, row-ID digests, keyed fingerprints and media integrity counts. Still treat it as operational information.

## Restoration acceptance, isolated staging only

1. Provision a separate staging document root, separate MySQL database, different storage directory and a non-public domain/network boundary. Never mount writable production media in staging.
2. Restore the backed-up MySQL schema/data and all private media into staging. Preserve the original APP_KEY and other necessary secrets with controlled access; disable outbound payment and email actions on the clone.
3. Before applying upgrade migrations, compare inventories on the restored clone:

       php artisan samith:recovery-inventory --compare=/secure/private/samith-before.json --strict

4. A failed comparison blocks release. Investigate missing or changed records, legal acceptances, licensed snapshots and missing/corrupted media. This inventory covers selected fields, not every database column or object store.
5. Apply target migration(s) to staging, inspect the migration status, and test login, creator permissions, draft/published content, free checkout, prompt entitlements and support tickets. Run another inventory after migration; if a migration intentionally changes selected fields, record and approve the expected differences.
6. Independently test a simulated interrupted upgrade. Restore the *matching* prior code + database + private files + APP_KEY into a second isolated environment, then verify user access, legal records, entitlements and prior functionality. Do not assume reverse migrations can roll back data.

## Production release and rollback controls

- Require a named human to approve the release and the verified backups.
- Use a separately built immutable release directory, validate package hash, and keep the webroot pointed at public/. Keep persistent storage and environment protected.
- Plan for maintenance, database migrations, queue draining and a reversible webroot switch. Avoid deleting the previous release until independently signed off.
- Rollback is a controlled restoration of matching code/data/media/secrets, never merely reversing source files after a schema change. Never run migrate:fresh against a customer database.
- Record rollback evidence and total recovery time before declaring customer hosting accepted.

## Queue reliability and worker supervision

- cPanel without persistent processes may remain on QUEUE_CONNECTION=sync, which runs work inside requests and is not appropriate for long operations.
- When using database/redis queue, configure a real worker supervisor on a hosting environment that supports it. Keep retry_after greater than worker timeout, configure tries/backoff appropriate to the job, and watch failed jobs.
- Example for an approved staging worker only (not run by this project):

       php artisan queue:work database --queue=default --sleep=3 --tries=3 --timeout=60 --max-time=3600

  A real supervisor/restart policy is still required. Never run this command as a long-lived unmanaged web process on shared hosting.
- Job enqueueing after a transaction commit is enabled for supported asynchronous drivers. This does not make arbitrary jobs idempotent. When adding email, media or fulfillment jobs, ensure duplicate-safe domain operations, limited retries, and secure failure logs.

## Runtime monitoring and access

- The admin-only /dashboard/system view and php artisan samith:infrastructure --strict provide credential-free runtime and queue counters. They cannot attest worker liveness, SMTP inbox delivery, payment compliance, offsite backup existence, external DNS, or a successful independent restore.
- Use php artisan samith:recovery-inventory for explicitly requested integrity inspections, not on every HTTP request; scanning media may be expensive.
- Monitor host disk space, MySQL backups, 5xx errors, failed queue jobs, worker restarts, TLS and package provenance in an operator-approved system. Never expose diagnostic endpoints with database credentials or private job payloads.

## Acceptance record

Record: environment, host, release Git SHA, package SHA-256, operator, date, backup location and integrity manifest, restore DB name, media integrity result, migration check, worker acceptance, recovery time, failed checks, remediation and signoff.

No production deployment is permitted solely because CI passed.

## Review history backup integrity

The recovery inventory v3 includes the new prompt_reviews table and a keyed content HMAC for reviewer notes, actors, actions and timestamps. A database clone restored from an older release must be migrated and get a new baseline before comparisons. Earlier v2 inventories intentionally fail format compatibility rather than falsely claiming review-history coverage.

## Saved prompts backup integrity (recovery inventory v4)

The feature milestone after creator review history adds a `prompt_saves` table. Inventory v4 fingerprints saved user/prompt relationships and migrations with a keyed HMAC, without disclosing customer identities or private prompt text.
A v3 inventory does not validate v4 data and intentionally fails format comparison; capture a fresh encrypted v3 pre-upgrade backup and new v4 post-migration baseline. The initial v2 encrypted backup predates both feature upgrades and is not a substitute for a current v3 backup.
