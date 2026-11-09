# Samith AI — reconciliation of already-installed saved insights files

Operator output from 2026-11-09 reports ALL 21 of 21 target file hashes present, with zero customizations and zero missing files. The previous --apply command failed its old-version preflight because it refused files that match the target version. Do not repeat the original --apply and do not re-copy source files.

The reconciliation utility rechecks the previously pinned upgrade script's Git blob hash and uses its reviewed 21-file manifest to confirm current production contents. It checks the encrypted v3 backup SHA256 before any operation.

The --check command reports whether the prompt_saves migration is PENDING or INSTALLED. It performs no database or code changes. The --finish command is idempotent: when BOTH table and migration record are absent, it enters a short Laravel maintenance period, runs only the additive prompt-saves migration, refreshes Blade/routes caches and exits maintenance. When BOTH are already present, it skips migration and maintenance. A mismatched migration state halts for manual inspection.

Both paths verify Laravel route registration, a strict healthy 15-table v4 recovery inventory, public homepage/catalog and CSS, and guest redirects for private workspace pages. No database restoration, table drop, payment enablement, Nginx changes or source installation occurs. The encrypted backup remains on the same production host, and a full independent restoration is not verified.

Source-reconciliation and unauthenticated-origin checks do not replace manual authenticated creator, customer and admin acceptance.
