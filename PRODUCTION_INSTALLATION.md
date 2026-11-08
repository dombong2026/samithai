# Samith AI — direct installation on samithai.com

**Deployment target: https://samithai.com** (production, aaPanel). A separate staging **domain** is not required by the owner. This does not waive safety checks or mean production has been installed.

## Current status

GitHub main provides a Laravel 13 production-compiled **development candidate** ZIP with vendor dependencies. The actual aaPanel Nginx site, DNS, PHP-FPM, database schema and live contents have NOT yet been independently inspected by this repository workflow. A blank browser page is not proof that web files, a MySQL database, private user data or an active service are absent.

## Step 1 — read-only audit on the actual aaPanel host

From a trusted server shell with a fresh checked-out samithai GitHub main repository:

    bash support/aapanel-production-audit.sh

This only checks the expected aaPanel site mapping, filesystem markers, PHP runtime, Nginx syntax and HTTP/DNS. It **never changes** Nginx, MySQL, web files, installation locks, email or payment. Preserve the output privately and confirm the actual vhost root and configured PHP-FPM version in aaPanel. Any BLOCK or REVIEW requires operator inspection; do not automatically delete files.

## Step 2 — verify the release

Download the CI release package for the exact commit and verify the ZIP SHA-256 against the matching workflow artifact. Do not use an unverified archive, an old release branch or a GitHub source-code ZIP without vendor and built assets.

Plan the new application release in its **own private directory outside the served webroot**, never overwriting the current site's files. Ensure Nginx will serve only its \`public/\` directory and enforce HTTPS. Keep the previous site configuration and any preexisting files intact as rollback evidence. On this new/blank site, create one fresh **empty** MySQL database through aaPanel with an app-specific user; never use a shared database or reuse a database with any existing tables.

## Step 3 — install directly on production after the audit

With the separate release directory prepared, confirm storage permissions, .env privacy and PHP requirements, then point the aaPanel Nginx **samithai.com** virtual host at the new release's \`public/\` directory and verify \`nginx -t\` before reload. Use the web-based five-step installer at:

    https://samithai.com/installer

Retrieve INSTALL_TOKEN only from the private .env on the production host. Enter the exact HTTPS application URL, a dedicated empty production database and the initial administrator. The installer creates/locks the install once; do not remove the lock to reinstall. No customer data should be created by test fixtures on production.

The installer currently configures \`QUEUE_CONNECTION=sync\`; leave it unchanged unless an independently supervised worker has passed on this host. Disable SAMITH_PAYMENTS_ENABLED, SAMITH_EMAIL_DELIVERY_ENABLED and SAMITH_SMTP_PROBE_ENABLED until their respective real acceptance processes. Never place a mailbox test token, credentials or SQL backup in GitHub.

## Step 4 — production smoke acceptance

Check HTTPS and valid certificate, public/ document root, blocked private URL paths, admin login, access control, user registration, draft/publish behavior, zero-cost checkout, persistent entitlements, media isolation, phone/tablet layout and real backups. Run the non-destructive host probe with the production domain as SAMITH_TEST_URL only **after installing**:

    SAMITH_HOST_CLASS=aapanel \
    SAMITH_DOCUMENT_ROOT=/actual/release/public \
    SAMITH_TEST_URL=https://samithai.com \
      bash support/host-acceptance.sh /actual/release

Record actual evidence, with all secrets redacted. The source-level CI tests and synthetic MySQL restore remain useful but are not evidence of an offsite production backup restoration. Save a complete encrypted database, media and APP_KEY backup under controlled retention after installation and verify restoration to a separately isolated environment when available; no staging **domain** is required.

## Release boundaries

A fresh production installation does not complete external SMTP provider onboarding, real payment integration, legal counsel review, native localization validation or actual-device signoff. Do not enable paid checkout or claim full commerce compliance from successful hosting installation. Actual installation requires an operator with aaPanel terminal access; the read-only browser connector cannot run terminal commands.


## Actual samithai.com Nginx cutover — guarded helper

The production source application is already prepared under:
\`/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49\`.

The root of \`samithai.com\` must still be \`/www/wwwroot/samithai.com\` and empty. Run:
\`bash support/aapanel-production-cutover.sh --check\` then (only after review)
\`bash support/aapanel-production-cutover.sh --apply\`.

The apply option backs up the Nginx vhost and rewrite include privately, changes only the root directive, checks Nginx syntax, reloads, and checks /installer + CSS at the local origin. If either check fails, it restores the original Nginx vhost and reloads. A public HTTPS HTTP result is reported separately and may involve Cloudflare or DNS; verify it before proceeding with installation.

This does NOT create or migrate a database, read out the install token, send emails, enable payments, or create an administrator. Create an empty dedicated MySQL database/user via aaPanel afterward and complete the web installer at https://samithai.com/installer. Never share the .env token or DB password in tickets/chats.
