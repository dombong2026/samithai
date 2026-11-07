# Samith AI — release gates (v0.0.1)

This is not a production acceptance certificate. Automated success indicates the evaluated code path, not an independent host or security audit.

## Source-level acceptance

GitHub workflows:
- .github/workflows/tests.yml: PHP syntax, framework routes, compiled Blade views, Laravel feature tests, Tailwind production build.
- .github/workflows/browser-mysql.yml: isolated MySQL 8 migrations and Playwright desktop/mobile-emulation browsing, auth, account and support workflows.
- .github/workflows/package.yml: release ZIP with vendor/, frontend assets, private .env excluded, checksum artifact.

## Security and correctness boundaries

- Media files are stored on the private local disk. Public reads require linking to published content or configured branding; otherwise owner or admin only.
- SVG, HTML files and executable uploads are not accepted. Media file limits and allowed MIME types are enforced server-side.
- Markdown/HTML CMS is sanitized on the server; raw scripts, forms and event handlers are stripped. Production security review remains.
- Creators manage their own media/prompts, while administrators manage CMS and role changes.
- Customer prompt entitlement content is snapshotted at checkout and cannot be replaced by creator editing.
- Audit events do not intentionally contain passwords or full financial credentials.
- Paid checkout remains disabled. Transactions are not to be fabricated; a real authorized processor and verified provider callbacks are required for paid entitlements.

## External acceptance not yet performed

1. Genuine provider sandbox onboarding, legally eligible merchant accounts, webhook signature/idempotency/reconciliation, verified refunds, chargebacks and payout approval.
2. Applicable digital-goods tax, invoicing, FX, creator royalty contract, consumer cancellation/refund rules and independent legal review.
3. Full native-language UI/policy review across en, zh, ko, ja, de, km, vi, th, id, ms, ar, fr and es; translation completeness.
4. Clean and upgrade installer acceptance on real cPanel and aaPanel, with SSL, backup, interruption/retry recovery and restoration tests.
5. Real iPhone/Android/tablet desktop browser acceptance, performance/load testing, malware/file validation and penetration testing.
6. Transactional SMTP, secure account recovery, email verification, merchant and customer communications.
7. Release operator approval, documented support boundaries, migration and rollback plan.

## Operational rule

Do not deploy to samithai.com or enable real payments on the strength of GitHub CI alone.
