# Samith AI release implementation plan — not executed

This branch provides admin editorial feature changes only. Do not deploy it until images and article content have individual human approval, restricted HTTPS browser/mobile acceptance, and an authorized production change window.

## Shared storage and rollback boundaries

- Active production release at the last operator inspection: `/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49`.
- Current production stores application data inside that release; **do not delete or move it in place**.
- Create a future shared root outside numbered release directories, e.g. `/var/lib/samithai-shared/storage`, after verified backups and permissions review.
- Share `storage/app`, `storage/framework/sessions`, `storage/framework/cache/data`, `storage/framework/cache/locks`, and `storage/logs` across releases. Keep `storage/framework/views` release-local. Keep environment secrets in a protected shared configuration location.
- The file-cache store must resolve the **same cache-data and cache-lock paths** from both old and new scheduler processes; test this with real permissions before cutover.
- Prepare new release in a separate directory; preserve previous Nginx root and maintain a documented rollback pointer and compatible schema. Verify media, sessions, login, SEO and article preview before cutover.
- Obtain fresh, checksum-verified backups of MySQL, protected media, sessions/configuration and current release just before any live migration. Historical backup components found on 2026-11-12 are insufficient as fresh cutover proof.

## Scheduler activation gate

- The Laravel task `samithai:publish-scheduled-editorial` is registered every minute with `withoutOverlapping(10)`. Real due-post publication and idempotency passed in isolated QA; this is not proof of production cron execution.
- The server cron service is active, but no Samith AI scheduler entry was confirmed in existing cron/aapanel wrappers. Do not target the isolated QA directory.
- **Only after production release approval:** create one reviewed minute-level scheduler entry under the application account `www`, targeting a stable active-release path. Suggested command form: `cd /PATH/TO/APPROVED/ACTIVE && /www/server/php/85/bin/php artisan schedule:run >> /dev/null 2>&1`. The precise path and ownership must be verified at cutover.
- Publication timestamps are currently UTC. Show the local-clock interpretation in the editorial UI before scheduling.
- Before enabling cron verify no unintended due candidates, current human approvals, appropriate cache locking and monitoring.

## Outstanding external acceptance

Restricted HTTPS real-browser login, mobile/Safari/Android viewport and device checks, media review, and actual human article approval remain pending. No production deployment or automatic publication is authorized by this document.
