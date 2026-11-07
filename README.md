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

## Working v0.0.1 foundation

- Public homepage, category browsing, prompt details, search and filtering.
- Registration, sign-in, sign-out, customer settings, creator opt-in.
- Shared admin/account visual shell with light and dark modes.
- Creator prompt drafts and CRUD; administrator approval and category CRUD.
- Customer cart, real zero-value order creation and entitlement-protected library.
- Locked first-install wizard with private installation token.

## Incomplete / not release-approved

Paid checkout, subscriptions, payment verification, media manager, rich HTML editor sanitization, advanced CMS, full multilingual routing, per-region localization, support/policy centers, configurable footer, installer QA and comprehensive account management. Never expose these as working features in the interface until implemented and tested.
