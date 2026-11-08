# Samith AI

Samith AI is a Laravel 13 digital marketplace for discovering, publishing, licensing, and managing reusable AI prompts.

- **Website:** https://samithai.com
- **Owner / Author:** Ton Samith — https://tonsamith.com
- **Version:** 0.0.1
- **Stack:** Laravel 13, Tailwind CSS, JavaScript
- **Workspaces:** /dashboard (administration), /account (customers and creators)
- **Hosting targets:** cPanel and aaPanel

## Status

Development foundation. Never treat this repository as a deployed, audited, payment-ready application without validating the installation, database, roles, web server, email, and payment-provider configuration.

## Licensing

Application source: proprietary, all rights reserved (see LICENSE.txt). Each marketplace prompt has its own customer-facing license; buying a prompt does not transfer copyright.

## Safety

Keep .env out of Git. Set the web root to `public/`. Never use test or simulated payment confirmation to grant paid downloads. Verify authorization server-side for all admin and creator actions.

## Installation

See [INSTALLATION.md](INSTALLATION.md). The web installer at /installer performs environment and database setup without SSH for the customer when dependencies are already packaged.

## Working v0.0.1 implementation (source-code level)

- Customer sign-up/sign-in, creator opt-in, customer library, and account settings.
- Public prompt discovery, categories, featured image/video/YouTube/color support.
- Creator draft and submission lifecycle; administrator category and prompt moderation.
- Customer cart, zero-value checkout and signed-in, entitlement-controlled private prompt library.
- Entitlement content snapshots protect licensed text from subsequent creator edits.
- Media Library with private, validated uploads; ownership-aware serving; search/filter and CRUD.
- Page Manager, Post Manager, Policy Center and Knowledge Base with sanitized rich-text editing, draft preview and publication.
- Support Center with owner/staff ticket conversations and close/reopen operations.
- Configurable footer of up to five columns; appearance settings (logo/site name/theme colors/hero).
- Customer orders and admin finance view, plus immutable-UI payment audit schema.
- Admin role management, system health screen and sensitive-operation audit trail.
- 13 supported locale prefixes with initial interface translations and language preference.
- Five-section web installer, installation lock and automated customer-package workflow.

## Verification

CI checks PHP lint, routes, compiled Blade views, Laravel feature tests and frontend build.
A separate integration workflow checks MySQL 8 migrations and Chromium desktop/mobile-emulation browser journeys.
Inspect current runs in https://github.com/dombong2026/samithai/actions and do not infer success from an earlier run.

## Remaining release restrictions

- **Paid checkout is disabled:** no real payment gateway has been onboarded or sandbox-verified; no refunds, recurring subscriptions, tax invoices or payouts are enabled.
- **Localization is partial:** initial shared UI translations exist, but dynamic content, policies, full account flows and native-language review are incomplete.
- **Customer readiness is unapproved:** password recovery/email verification, full security review, source/code quality acceptance, cPanel/aaPanel real clean-install and upgrade tests, and physical-device browser testing remain.
- **Legal terms need review:** draft policy pages and regular/extended license identifiers alone do not establish legally accepted product license terms.
- **Package is development-only** until independent installation and restore drills pass.
- There are no mock transactions, demo accounts or simulated payment confirmations.
- GitHub changes do not deploy to samithai.com automatically.

Do not activate unverified payments or represent this application as a production-approved marketplace.


## Production-hardening milestone

- Password reset and signed email verification routes. Delivery requires explicit enablement and a non-log outbound mail transport; SMTP/inbox acceptance is external.
- Optional email verification enforcement for checkout, creator submission and uploads.
- Payments remain disabled. The internal fulfillment boundary validates settled provider-confirmed amount, currency, immutable order snapshots and duplicate transaction references, but no provider callback is connected.
- Legal version control: draft/immutable published license and policy versions, explicit review references, and optional per-order acceptance records.
- Translated CMS drafts, review gates, localized route handling and account/auth vocabulary for 13 languages. Human review and complete UI translation remain pending.
- CI separately validates real HTTP first installation on fresh MySQL, restart and administrator login.
- See HOST_ACCEPTANCE.md, DEVICE_ACCEPTANCE.md, LEGAL_REVIEW_CHECKLIST.md, PAYMENT_PROVIDER_ONBOARDING.md and LOCALIZATION_REVIEW.md.
- Read-only host preflight: support/release-preflight.sh. No actual customer host or physical phone has been independently tested.

All release flags default to false. Never enable paid checkout through environment configuration alone; a reviewed provider adapter, merchant onboarding and sandbox settlement acceptance are mandatory.


## Infrastructure diagnostics (development milestone)

- Use `php artisan samith:infrastructure --json` after installing the application to inspect local runtime readiness without printing database passwords, application keys or tokens.
- Use `php artisan samith:infrastructure --strict` to return a failure exit code for critical runtime checks. The administrator System Center shows the same checks and explicitly separates warnings from failures.
- The checks only read runtime configuration, paths and a `SELECT 1` query; they do not migrate, delete files, send emails or issue external HTTP calls. On first bootstrap before installation, Laravel's existing installer may initialize `.env`; use the standalone `support/release-preflight.sh` for a strictly read-only pre-install inspection.
- The web installer configures secure session cookies automatically for an HTTPS `APP_URL`. Existing HTTPS installs should independently verify `SESSION_SECURE_COOKIE=true`.
- `composer.lock` and `package-lock.json` are tracked and were generated by a dedicated dependency-audit, locked-reinstall and automated-test workflow. Standard CI uses `composer install` and `npm ci` for deterministic installation; review future lockfile updates and external supply-chain acceptance independently.
- Composer dependency resolution is constrained to the minimum PHP 8.3 platform through `composer.json` to avoid accidentally locking PHP 8.4-only dependencies. When the dependency manifests change, rerun the `Resolve and verify dependency lockfiles` GitHub workflow and verify the PHP 8.3/8.4/8.5 matrix before release.
- A clean diagnostic report is **not** a substitute for SMTP delivery, merchant onboarding, security review, cPanel/aaPanel recovery drills or physical-device testing.


## Recovery, queue and monitoring development

- The installer refuses every non-empty MySQL schema, including tables left by an interrupted install. Recover or recreate a new empty database instead of retrying against unknown existing data.
- The administrator System Center and samith:infrastructure command provide read-only queue backlog, stale reservation and failed-job counts. They do not read job bodies or prove any queue worker is alive.
- Database and Redis queues use after-commit dispatch. Shared-hosting installs continue to start with the synchronous queue; switching to a background queue requires an independently supervised worker, retry and failure policy.
- samith:recovery-inventory --json --strict captures selected table counts and ID fingerprints, plus checks for missing or checksum-mismatched private media. To compare a separately restored clone, run samith:recovery-inventory --compare=/secure/path/source-inventory.json --strict on that clone.
- The disposable GitHub Actions recovery drill creates artificial customer/license records, exports MySQL and media, restores into a second disposable MySQL database, checks fingerprints and media, reruns migrations, and verifies that tampering fails.
- An inventory cannot prove full row-content integrity, an older-version upgrade, storage outside the private local disk, or a complete independently accepted cPanel/aaPanel restore. Keep database dumps, private media and .env/encryption keys encrypted and outside webroot; never upload actual backups to CI artifacts.
- Every production deployment still requires a human-approved, off-host backup, a staging restoration, verified rollback procedure and separate operations acceptance. No automatic production restoration or rollback has been installed.
