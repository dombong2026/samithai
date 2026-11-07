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
