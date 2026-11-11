# Samith AI — SEO Manager, Editorial Workflow and Featured Images
November 2026. DEVELOPMENT ONLY. Production remains unchanged until approved deployment.

## Implemented components

- SEO Manager: search title, meta description, internal canonical, social title/description, OG image, robots index/noindex, and switchable safe JSON-LD. BlogPosting, WebPage and Product types come from actual content. No invented prices, inventory, ratings or reviews.
- Editorial Workflow: administrator reviewer assignment, draft/review/changes requested/approved/scheduled stages, mandatory written findings, publication scheduling and cancellation, private revision snapshots with inspection and draft-only restoration.
- Schedule command: publishes only unchanged, approved, due post/page records; skips unapproved records and never changes prompt products. Requires separately verified server cron.
- SEO seed command: samithai:seed-educational-seo --check --author-email=EMAIL is read-only; --apply inserts missing editorial SEO suggestions for eight existing posts without publishing them or overwriting edits. Back up MySQL before any production writes.
- Eight WebP featured image *design drafts* are available in the separate file samithai_8_featured_image_drafts.zip (not auto-attached). Each is about 412x464 pixels and includes a conceptual S emblem. These are vertical illustration crops from one 2x4 ImageGen sheet, not approved brand artwork or high-resolution wide-format final covers. Human approval required before media upload; verify the emblem is acceptable, or replace it.

## Next six articles — no publication authorized by this upgrade

1. 20-practical-ai-prompts-for-small-businesses — verify 20 templates, claims, business/privacy assumptions
2. how-teachers-can-use-ai-to-prepare-lesson-plans — curriculum, student privacy, translation
3. how-to-create-a-professional-website-using-ai — technical facts, release and security assertions
4. 15-ai-prompts-for-social-media-marketing — all 15 examples, rights and marketing claims
5. how-to-test-and-improve-an-ai-prompt — evidence and quality terminology, no fabricated testing
6. ai-prompt-engineering-vs-ai-agents — tool boundaries, security and actual platform features

Each remains a draft until a person reviews its complete content, SEO text, category destinations, original featured media, and responsive private preview; assigns a reviewer; records a decision; and explicitly publishes or schedules. Original 30 prompt products retain their entirely separate quality-review gates. No actions in this upgrade automatically approve content.

## Production acceptance gates

1. Reconcile exact GitHub SHA, source hashes and deployment code; use isolated MySQL and private-media clone for an additive migration rehearsal.
2. Back up MySQL with verified archive and preserve application source backup before any database migration.
3. CI: Laravel feature tests, PHP compatibility, browser/MySQL, queue-worker acceptance, release packaging and recovery.
4. Confirm administrative SEO and editorial routes reject guests/unauthorized users; validate private revision and draft OG media access.
5. Verify current published six informational pages and two introductory posts remain published and return HTTP 200.
6. Test robots/canonical/OG/JSON-LD on published page, post and prompt; no draft article body or prompt content in search metadata.
7. Confirm changed draft invalidates approval and scheduled publishing never publishes it. Check real cron and physical browser/device behavior.
8. Check eight image concepts for branding, rights and fit before any upload through Media Library. Attaching to an already-published article requires controlled unpublish, re-review and republication.
9. Preserve .env, nginx, payments/licensing, public layouts and prompt moderation untouched.
10. No automatic full database restore after migration; restoring production over concurrent writes risks deleting unrelated data.

## Rollout boundary

This branch is code review, not production deployment. Merging and deploying require independent QA and the owner's authorization. Neither code installation nor SEO seed should publish the six pending articles. Keep the first two existing articles and six informational pages live.
