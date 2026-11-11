# Samith AI — Reviewed editorial publication release

Editorial scope: Publish six complete informational pages first, then publish the two introductory educational articles individually. The remaining six blog articles and all 30 starter prompt products stay unpublished.

## Required human approval

This release does not publish as part of installation. The administrator must preview every page/post, check factual claims, links, safety and rights, approve business and legal wording, and review mobile/desktop layouts before typing the release confirmation. An automated source audit cannot substitute for those decisions.

## Verified source and link review

- Six informational pages: About Samith AI, How It Works, Become a Creator, Prompt Quality Standards, AI Tools & Compatibility, Responsible AI Usage.
- Pages cross-link to these same six pages and established website routes: catalog, registration, support, account, and policies.
- Article one: What Is an AI Prompt? A Beginner’s Guide to Better Results, linking to Business & Productivity, Education & Teaching and How It Works.
- Article two: How to Write Better Prompts for ChatGPT, Claude, and Gemini, linking to Business & Productivity, Coding & Development and Prompt Quality Standards.
- Actual AI-provider tests, legal approval, browser rendering and public content accuracy cannot be established from static source alone.
- The publishing command rejects content drift and unexpected owners, missing categories, disabled referenced public features and publication out of order.

## Publication sequence

1. Install the source command with support/deploy-reviewed-content-publication.sh --check and then --apply. This does not mutate the database.
2. Run support/release-reviewed-editorial-content.sh --check. No writes.
3. Review private previews in Dashboard > Pages. After owner/editorial acceptance, run support/release-reviewed-editorial-content.sh --pages and type PUBLISH-SIX-REVIEWED-PAGES when prompted. A fresh MySQL backup precedes the atomic six-page publication.
4. Verify all six public URLs, including mobile/desktop and localized English rendering.
5. Review the first blog post in Dashboard > Posts. Run the release script --first and type PUBLISH-FIRST-REVIEWED-ARTICLE. A separate backup precedes that publication.
6. Verify the first public article URL and link destinations.
7. Independently review the second post, run --second with PUBLISH-SECOND-REVIEWED-ARTICLE, and verify its public URL.
8. Repeat --check: expect six published pages, two published introductory articles, and six remaining draft articles.

The command writes content.status audit events tied to the administrator and stage. Each stage is idempotent; no duplicate audit event when rerun. The script checks local-origin HTTPS response status for every published URL.

## Release boundaries

No database migrations, public layout redesign, new navigation/footer links, SEO schema, payments, licenses, starter prompt moderation, or external AI integrations. No automatic publication of the other six articles. The existing CMS can still edit and unpublish entries through authorized controls.

Suggested SEO fields remain in the source JSON; the current Post Manager has no dedicated SEO title or description columns. Linked product categories may show empty states until prompt products pass human review. Do not advertise unapproved products, and verify cross-links to published pages before sharing the article URLs.

## Backups, rollback and limitations

Each publication stage requires a verified MySQL dump stored in a root-only directory. Do not automatically restore an entire production database after a publication error, because that can delete unrelated concurrent writes. Use the authenticated Page/Post Manager to unpublish an individual entry after assessing effects. Keep the backup and audit events until acceptance is complete.

Static and CI checks cannot establish production authenticated browser acceptance. An administrator must perform a real-browser review and explicit release attestation. The platform/browser tools available to ChatGPT cannot directly publish into the aaPanel app.
