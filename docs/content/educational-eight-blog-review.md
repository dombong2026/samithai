# Samith AI — First Eight Educational Blog Articles

Editorial batch: samithai-education-blog-2026-11
Editorial state: eight complete English-language articles, drafts only.
Target: existing /dashboard/posts Post Manager. Public /blog routes only after approval.
Publication: separate administrator action; never automatic.

## Article inventory and SEO direction

| # | Article title | Primary keyword | Approximate words |
|---|---|---|---:|
| 1 | What Is an AI Prompt? A Beginner’s Guide to Better Results | what is an AI prompt | 1068 |
| 2 | How to Write Better Prompts for ChatGPT, Claude, and Gemini | how to write better AI prompts | 1138 |
| 3 | 20 Practical AI Prompts for Small Businesses | AI prompts for small businesses | 1181 |
| 4 | How Teachers Can Use AI to Prepare Better Lesson Plans | AI lesson planning for teachers | 1073 |
| 5 | How to Create a Professional Website Using AI: From Brief to Launch | how to create a website using AI | 1184 |
| 6 | 15 AI Prompts for Social Media Marketing That You Can Adapt | AI prompts for social media marketing | 1087 |
| 7 | How to Test and Improve an AI Prompt: A Practical Quality Checklist | how to test an AI prompt | 1239 |
| 8 | AI Prompt Engineering vs. AI Agents: What’s the Difference? | AI prompt engineering vs AI agents | 1340 |

Approximately 9,310 words of original editorial content, including worked examples and CTA paragraphs.

## Source files

- database/content/educational-blog-2026-11/01-fundamentals.json: articles 1–2.
- database/content/educational-blog-2026-11/02-business-and-teaching.json: articles 3–4.
- database/content/educational-blog-2026-11/03-website-and-social.json: articles 5–6.
- database/content/educational-blog-2026-11/04-quality-and-agents.json: articles 7–8.

Each JSON record contains the full body_html, title, slug, excerpt, SEO title, meta description, primary keyword, audience, complete H2 outline, linked product categories and review notes.

CMS limitation: current ContentEntry/Post Manager persists title, slug, excerpt, body HTML, author and publishing state. Dedicated SEO title, meta description and blog-category columns do not exist. The SEO suggestions and outlines are retained in the editorial JSON, not silently discarded or falsely presented as published metadata. Category links point to existing prompt product category routes, not additional blog-category relationships.

## Draft import and publication sequence

1. Install the verified source with support/deploy-educational-blog-drafts.sh --check then --apply; no database writes occur at this stage.
2. Run support/import-educational-blog-drafts.sh --check with an existing administrator email. This validates 8 articles, active categories, ownership and conflicts without mutations.
3. Run the backup-first --apply only after an authorized production preflight. It verifies a MySQL backup, then inserts only missing ContentEntry(kind=post, status=draft) rows in one database transaction.
4. Review all eight entries at https://samithai.com/dashboard/posts?status=draft using the private Preview function.
5. Check original content, accuracy, internal category URLs, language, readability, accessibility, model compatibility and the latest production capabilities.
6. Add suitable, licensed featured images through the existing Media Library if desired; none are generated or attached automatically.
7. Publish individually through Post Manager only after acceptance. Verify /blog and each article URL in an ordinary browser.

## Editorial acceptance

- Verify AI provider references and avoid claiming official partnerships, universal compatibility or guaranteed outcomes.
- Review the 20 business and 15 marketing prompt examples for factual, licensing, privacy and platform-compliance concerns.
- Teacher article: instructional reviewer should assess age appropriateness, learning outcomes, accessibility and privacy.
- Website article: technical reviewer should check scope, defensive security and release advice.
- AI agents article: avoid representing Samith AI as an operational tool-connected agent platform.
- Linked categories must be active; a category with no published products can still display an empty state. Review the user experience before linking to it.
- Cross-page references to How It Works, Quality Standards and Responsible AI Usage depend on those six previously drafted website pages being published. If they remain drafts, edit or delay the corresponding CTAs.
- Article eight contains a cross-link to article one: publish article one before article eight, or adjust the link.
- Suggested SEO metadata remains in source files until a supported metadata manager exists.
- English is the reviewed source language. Any Khmer or other translation needs language review before publication.
- Record editor name, date, review status and outstanding changes. The import itself is not an editorial approval.

## Safety and rollback

Source installation is separate from database import. No migrations, public UI changes, checkout changes, product changes, automatic content publication or updates to existing posts. Import requires an admin owner, rejects title/slug conflicts and user-edited or published records, and uses one database transaction. Keep the pre-import backup until acceptance is complete. Do not bulk-delete drafts without first reviewing human edits and any references.
