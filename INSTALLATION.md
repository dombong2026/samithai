# Samith AI v0.0.1 — installation guide

## Important status

This repository contains the actual Laravel 13 source code, not a completed customer-ready distributable ZIP. Build with Composer on a trusted machine and include the resulting vendor dependencies in a validated release package before advertising no-CLI shared-hosting installation.

## Prerequisites

- PHP 8.3+ and the PHP extensions listed by the web installer.
- MySQL server; one freshly created empty database and a database user with appropriate privileges.
- Webserver document root pointing **only** at the Laravel public/ directory.
- HTTPS in production, writable storage/ and bootstrap/cache/, and a private writable .env.
- A complete release that includes Composer vendor/ dependencies.

## Shared hosting (cPanel) / VPS (aaPanel)

1. Upload the approved release into a directory outside the publicly served webroot. Point the domain document root to the release's public/ directory.
2. Confirm vendor/autoload.php exists. Do not serve .env, storage/, vendor/, git data or Laravel project root.
3. Create an empty MySQL database and associated user. Configure PHP extensions and permissions, especially storage/ and bootstrap/cache/.
4. Open /installer over HTTPS. The first-boot bootstrap securely creates a private .env (from .env.example), a random APP_KEY, and INSTALL_TOKEN if needed.
5. Find INSTALL_TOKEN in the private .env using File Manager (or SSH on VPS). Enter the token, site URL, MySQL connection details, and first administrator account. Press Install Samith AI once.
6. The installer checks MySQL, migrates tables, creates the administrator, saves .env and locks the installer. Sign in at /login and use /dashboard.
7. Test authentication, customer/creator restrictions, prompt moderation, free checkout and private library access on that specific host. Check mail delivery and real-world mobile browsers separately.

Do not put the project root directly under a public web document root. Do not use an existing database with users for a fresh installation. Do not delete storage/app/installed.lock to reinstall an existing site.

## Admin account

Only the first-install flow creates an administrator. Self-registered accounts remain customers; customers may opt into the creator workspace.

## Current commerce boundary

Paid prompt listings can be created, but paid checkout is intentionally disabled. Only truly free prompts can produce completed orders and entitlements. Do not take money, expose private prompt content, or manually mark payments completed without verified provider integration.

## Developer QA

From a trusted development machine with Composer, Node and the necessary PHP extensions:

    composer install
    cp .env.example .env
    php artisan key:generate
    php artisan migrate
    php artisan test
    npm install --ignore-scripts
    npm run build

Set APP_ENV=testing and use an isolated database when running tests. Never run tests on a production database.

## Outstanding release gates

Independent cPanel and aaPanel fresh installation tests, installer interruption/retry recovery, package building with locked dependencies, password reset and email verification, media storage security, localization/translation review, verified payment sandbox, receipts/tax compliance, refunds, licensing enforcement and complete admin tools are not yet accepted.
