# Samith AI — External Release Acceptance (unapproved)

This is a **fail-closed evidence ledger**, not a completed acceptance certificate and not deployment authorization. GitHub CI, source tests and browser emulation do not substitute for independent operator, provider, counsel and actual-device acceptance.

## Evidence workflow

1. Use the exact approved commit from GitHub main and record its 40-character SHA.
2. Build the ZIP, verify its SHA-256 independently and record its 64-character hash.
3. Copy \`support/acceptance/pending.json\` into a **private, operator-controlled location outside the repository and webroot**.
4. For each external gate, a qualified operator performs the test on a disposable or separately approved real environment, records a timestamp, evidence digest and an opaque evidence reference to securely retained screenshots/logs/reports. Do not put passwords, APP_KEY, tokens, actual database dumps, personal data or screenshots containing secrets in Git.
5. When results are independently checked, set a gate to "accepted" and fill reviewer, reviewed_at (UTC ISO 8601), evidence_sha256 and evidence_reference. Failed tests use "failed"; untested gates stay "pending".
6. Run \`php support/acceptance/check.php /secure/path/manifest.json\`. It exits nonzero until every gate record is structurally complete. **A successful exit proves only that the ledger is filled; not that the evidence is authentic or correct.** Human signoff and production change authorization remain separate.

The source-controlled manifest must stay pending; CI asserts it is blocked.

## Independent gate definitions

- **aapanel_clean_install**: fresh isolated aaPanel release using the actual assembled ZIP, PHP, MySQL, HTTPS, document root, locked installer, auth, free order/entitlement, permissions and web exposure checks.
- **cpanel_clean_install**: separate actual cPanel shared-hosting installation, including no-CLI install, redirects, permissions, memory limits, upload limits, PHP extensions and independent app access.
- **prior_release_upgrade**: *actual prior committed release* + real migrated staging clone; verify existing users, legal versions, entitlements, media and fully rehearsed rollback. Idempotent current-schema migrations alone do not satisfy this.
- **offsite_backup_restore**: encrypt and copy full DB + private storage + keys to an off-host destination with restricted retention; download a copy; restore into an entirely separate environment; compare inventory, data and integrity; log time, operator and storage service. No credentials in evidence.
- **queue_worker_supervision**: on an aaPanel-compatible staging host prove worker restarts, supervised processes, persisted queue after DB commit, retries/failures, liveness alerts and cron/scheduler as applicable. Synchronous cPanel queues do not count as a running worker.
- **smtp_inbox_delivery**: configure an eligible real provider in staging, verify SPF/DKIM/DMARC and sender domain, send to a controlled mailbox, observe headers, TLS, deliverability, resets, verification, bounce/retry and token secrecy. A configuration check does not count.
- **payment_provider_and_tax**: verify the business is eligible for actual provider onboarding; use official sandbox credentials, signed settlement verification, idempotency, merchant ID, amounts, currency, refunds/chargebacks, invoices, tax and royalty accounting reviewed by professionals. **Never enable paid checkout on these source tests alone.** For Cambodia, inspect official Bakong Open API/KHQR documents and provider eligibility before integration; Stripe's country list currently does not include Cambodia.
- **localization_native_review**: complete UI and dynamically rendered content in all 13 locales, no missing keys, proper script direction, pluralization, policy parity, native language screenshots and reviewer signoff by language/version.
- **legal_counsel_approval**: qualified Cambodia-appropriate counsel reviews actual published Regular/Extended licenses, terms, refunds, privacy, IP/content rights and cross-border digital commerce, with versioned approval and effective date.
- **physical_device_acceptance**: actual iPhone, Android and tablets with different screen sizes, Safari/Chrome, theme switching, accessibility, auth/verification, media and purchase boundaries; evidence must identify device and OS/browser versions.
- **independent_security_review**: permission/IDOR and CSRF tests, failed-installation cleanup, rate limits, sensitive URL exposure, dependency audit, file upload and HTML sanitization, backup/storage key security, session lifecycle, TLS and incident response; independent findings resolved with regression evidence.

## Publicly documented payment considerations

- Stripe's official global list: https://stripe.com/global — verify merchant support at the time of onboarding. Presence on a global documentation site is not merchant eligibility.
- National Bank of Cambodia Bakong official API: https://api-bakong.nbc.gov.kh/ ; documentation: https://sit-api-bakong.nbc.gov.kh/document .
- These links are references, not authorization to accept real money. The code currently has no approved payment adapter; keep the payment flag disabled.

## Do not claim a production pass from CI

Package build and executable tests use disposable services and synthetic data. Neither is proof of a separate real aaPanel or cPanel customer installation, offsite retention, physical devices, external inbox delivery, or legal approval.
