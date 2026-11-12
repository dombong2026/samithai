# Samith AI SEO and Editorial Workflow — Production Cutover Protocol

**Status:** Prepared in PR #6, not executed. The production source still runs from a pinned release directory as observed November 12, 2026. Six featured images are already associated with six production article drafts. Their social previews must inherit those images automatically; do not upload the same files again.

## Core structure

- Versioned code: `/www/wwwroot/samithai-releases/<approved-commit>/`
- Future stable active pointer: `/www/wwwroot/samithai-current` (symlink, not current production's ordinary `/www/wwwroot/samithai.com` directory)
- Private persistent state: `/var/lib/samithai-shared/storage` (restricted `www` ownership and permissions)
- Shared paths: `storage/app`, `storage/framework/sessions`, `storage/framework/cache/data`, `storage/framework/cache/locks`, `storage/logs`
- Per-release path: `storage/framework/views` (compiled Blade views must not be shared)
- Shared protected `.env` (never under `public/`), with release-specific application configuration cache generated after the target is finalized
- Nginx root *after authorized cutover*: `/www/wwwroot/samithai-current/public`. Currently Nginx points directly to the pinned release and must not be silently reconfigured.
- Cron *after authorized release*: a single per-minute `php artisan schedule:run` run as `www` from the stable pointer. The existing cron inventory did not find a Samith AI scheduler entry.

## Controlled transition — implementation steps

1. Complete independent restricted HTTPS, mobile and live-device acceptance with synthetic staging data. Before production mutations, establish a fresh, verified SQL and private-media backup, copied offline as needed, and a restore plan.
2. Inspect the active Nginx site root, active PHP-FPM, existing session configuration and all current aaPanel cron entries, including wrapper scripts, to avoid duplicate schedulers.
3. Build the approved GitHub commit under a **new** versioned release directory; never modify the currently served release in place. Verify vendor assets, Laravel PHP extensions and GitHub provenance, and test additive migration plans using an authorized isolated clone first.
4. At an approved maintenance window, stop the existing scheduler and quiesce app writes. Make the final consistent backup and copy existing production `storage` into shared protected storage, preserving original permissions and checksums. Protect `.env` and installation-lock state. Verify each media image still exists and all six draft associations survive.
5. Link the new release's persistent directories to shared storage; keep compiled views local. Review the effective Laravel `cache.stores.file.path` and `lock_directory` so old/new scheduler processes share the **same** lock store. Drain old processes before switching, because the original release currently uses release-local locks.
6. Apply only approved additive migrations (`2026_11_11_000200_create_seo_editorial_workflow`), with a before/after row count and fingerprint report. No reset, truncate, `migrate:fresh` or legacy payment/notification activation.
7. Seed missing *eight* existing educational SEO records using `samithai:seed-educational-seo` only after its read-only `--check` succeeds, the administrator identity is correct, and its no-overwrite guard confirms existing edits are preserved. No publication occurs.
8. Run `samithai:review-six-educational-drafts --json` for the six drafts: verify the six featured-media references, source-content differences, SEO metadata and default social-image fallback. Human approval remains separate.
9. Point a stable symlink to the reviewed release, change Nginx's **one** root directive to the stable symlink path, validate configuration, reload gracefully, and revalidate browser login, session persistence, private media access and public pages.
10. Install the single minute-level scheduler *after* the new release and lock storage are verified. Confirm no due candidates or unapproved draft publication; only explicitly approved content may publish. Timestamps are UTC.
11. Record the final commit, backups, Nginx config hash, DB version, shared-storage checksum, cron config, validation results and rollback release location. Retain the old release.

## Rollback

- Disable the new Samith AI cron entry, drain in-flight scheduler/FPM workers, and use the stable symlink to point to the retained old application release.
- Keep shared storage and protected configuration intact. Never delete media or roll back SQL migrations automatically; verify schema compatibility with the older code. If incompatible, use a verified backup restoration procedure during maintenance.
- Validate Nginx syntax, restore the corresponding old root/pointer safely, reload Nginx and confirm login, public pages and media. Retain logs and release manifests for forensic review.
- Never publish six article drafts or 30 starter prompt products to test rollback.

## Current open gates

- Human editorial and rights approval for each six article
- Restricted HTTPS, iPhone/Android/tablet and Safari browser acceptance
- Fresh independent backups and storage cutover authorization
- Stable pointer and cron deployment after new release acceptance

The script `support/samithai-editorial-cutover-plan.sh` implements **read-only topology review only**. It cannot migrate, switch symlinks, install cron or deploy. This is intentional.
