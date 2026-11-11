# Samith AI — Six essential public information pages

Collection: samithai-core-pages-2026-11
Editorial state: Complete English-language drafts awaiting review and publication.
Published state: None are published by the import workflow.
Website layout: Uses the existing Page Manager and public ContentEntry template unchanged.

## Page inventory

| Page | URL slug | Source |
|---|---|---|
| About Samith AI | about-samith-ai | 01-platform-and-creators.json |
| How It Works | how-it-works | 01-platform-and-creators.json |
| Become a Creator | become-a-creator | 01-platform-and-creators.json |
| Prompt Quality Standards | prompt-quality-standards | 02-trust-and-guidance.json |
| AI Tools & Compatibility | ai-tools-and-compatibility | 02-trust-and-guidance.json |
| Responsible AI Usage | responsible-ai-usage | 02-trust-and-guidance.json |

Both full-length editorial files are in database/content/essential-pages-2026-11/. Each contains title, slug, excerpt, full HTML body, suggested SEO title, suggested meta description, and review notes. The existing CMS supports title, slug, excerpt, and body HTML. Suggested SEO fields remain in source, not in the database, because the current Page Manager does not have dedicated metadata columns.

The text references existing platform capabilities and avoids invented metrics, creator payouts, model partnerships, guaranteed results and unapproved promises. All internal links are absolute HTTPS to satisfy the existing SafeHtml sanitizer.

## Import sequence: no automatic publication

1. Install source using support/deploy-essential-page-drafts.sh --check then --apply. No database writes.
2. Run support/import-essential-page-drafts.sh --check. No writes.
3. Run support/import-essential-page-drafts.sh --apply after authorization. This verifies a protected MySQL backup and creates six draft CMS records inside a transaction.
4. Open https://samithai.com/dashboard/pages?status=draft and use Preview on every page. Confirm content, facts, and cross-links.
5. Publish individually with the existing Page Manager only after human review. The scripts do not change footer/header navigation. Footer Manager links should be added after publication if desired.

Private preview uses the existing /dashboard/content/{id}/preview route. Public /pages/{slug} and localized routes cannot display unpublished drafts.

## Editorial approval checklist

- About Samith AI: approve brand and ownership wording.
- How It Works: verify cart, saving, library, prompt access, account and creator flow.
- Become a Creator: verify eligibility, moderation, terms, licensing, and avoid any promise of payouts or guaranteed approvals.
- Prompt Quality Standards: verify Starter 30 review gate text; no unearned certification or AI testing claims.
- AI Tools & Compatibility: verify current external examples and preserve the non-affiliation disclaimer.
- Responsible AI Usage: ensure guidance complements actual legal/privacy/license policies, not replacing them.
- All pages: review mobile/desktop rendering, links, plain-language accessibility, and approved English copy.
- Translations: commission native review for Khmer/other languages before publishing localized variants.
- SEO: use suggested metadata when a future supported SEO manager exists; do not imply the current CMS stores it.
- Publish with care: unapproved page links should not be advertised in menus, and linked pages should be checked in the proposed release order.

## Safety properties

No existing page is overwritten. Existing slug, title, owner or content conflicts block the import. All created records are kind=page, status=draft, published_at=null. No migrations, public redesigns, payments, orders, prompt moderation or external AI integrations are modified. Keep the MySQL backup until review is complete.
