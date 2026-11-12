# Six educational articles — source review, November 12, 2026

This is a **source-content review and development checklist**, not an approval to publish. The source material is the three JSON bundles in `database/content/educational-blog-2026-11/`. Live database records may have changed after import; run `php artisan samithai:review-six-educational-drafts --json` after the SEO migration to compare source content and image links, without writing to the database.

| Article | Words | SEO title / description characters | Original WebP selected | Primary editorial follow-up |
| --- | ---: | ---: | --- | --- |
| 20 Practical AI Prompts for Small Businesses | 1,181 | 56 / 134 | 01_small-business-ai-prompts | Confirm all 20 examples survive sanitization; business terms and Khmer examples need human review |
| How Teachers Can Use AI to Prepare Better Lesson Plans | 1,073 | 60 / 152 | 02_teachers-ai-lesson-plans | Verify lesson objectives, classroom suitability, accessibility and student-data handling |
| How to Create a Professional Website Using AI: From Brief to Launch | 1,184 | 53 / 138 | 03_ai-website-from-brief-to-launch | Developer review of security, accessibility, deployment and “tested vs generated” claims |
| 15 AI Prompts for Social Media Marketing That You Can Adapt | 1,087 | 52 / 141 | 04_social-media-ai-prompts | Confirm 15 distinct usable templates and no implied advertising-platform approval |
| How to Test and Improve an AI Prompt: A Practical Quality Checklist | 1,239 | 54 / 135 | 05_ai-prompt-quality-checklist | Reconcile described quality process with current production policy and limitations |
| AI Prompt Engineering vs. AI Agents: What’s the Difference? | 1,340 | 52 / 135 | 06_prompt-engineering-vs-ai-agents | Technical review: do not claim that Samith AI itself is an operational multi-tool agent platform |

All six source items are full articles with H2 outlines, unique keywords, recommended SEO titles/descriptions, links and review notes. SEO title lengths are 52–60 characters and meta descriptions 134–152 characters. Counts are measured from source bundles, **not confirmed against edited live content**.

## Observed editorial risks

- The small-business and social-marketing articles include numerous `[PLACEHOLDER]` fields inside reusable prompt templates. This is intentional for the templates; verify that no placeholder appears as an unfilled factual claim in surrounding explanatory prose.
- The teacher article requires subject-matter and bilingual-language review before publication; automated language checking is not a substitute.
- The website and agents articles describe technical practices whose security and product-capability claims must be checked against currently deployed behavior.
- Links reference public category routes and previously created informational pages. Verify each destination is published/available; the agents article also links to the beginner blog, which should remain published first.
- Featured images have been associated with all six live drafts by an authenticated administrator. The app's existing `SeoHead` helper automatically uses each assigned featured image as the Open Graph/Twitter/schema image unless a separate social image is selected.
- The public Media Library serves images referenced by **published** content; draft-only images remain private. Do not assume private draft previews can be scraped by social networks.
- These checks cannot independently verify claims, rights, translation accuracy, current URL health or editorial quality. A human reviewer must inspect each full draft, metadata, image and live-preview output.

## Review and release sequence

1. Stage PR #6's additive SEO/editorial migration; verify backup and migration rehearsal. Never run `migrate:fresh`, import scripts or code deployment directly on production without approved rollback readiness.
2. Run the existing `samithai:seed-educational-seo --check --author-email=ADMIN_EMAIL` against the reviewed release. When approved, `--apply` creates **only missing** SEO rows for eight educational posts (including the two already published), with no publication. Existing custom metadata must not be overwritten.
3. Run `samithai:review-six-educational-drafts --json`. Examine any source differences, missing file references or SEO values.
4. Open each draft in Post Manager, SEO Manager and Editorial Workflow. Confirm human review of content, SEO, internal links, subject-matter detail, image licensing and the mobile share preview. Record reviewer notes and checklist; request revisions where needed.
5. Keep all six drafts unpublished until the reviewer individually approves them. The 30 starter prompt products remain subject to their own separate review gates.
6. Prepare release-aware shared private storage, compiled views per release, and a stable active-release pointer; verify fresh backups and rollback before scheduling the production cron.

No publication, SEO migration, cron installation, or production cutover is performed by the source-review document.
