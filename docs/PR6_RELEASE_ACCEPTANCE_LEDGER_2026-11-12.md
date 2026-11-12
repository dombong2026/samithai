# Samith AI — PR #6 Production Release Acceptance Ledger
**Prepared:** 2026-11-12  
**Disposition:** DRAFT / NOT AUTHORIZED TO MERGE OR DEPLOY  
**Production:** `samithai.com`, Laravel, current pinned release `3c9bf3fda1d0df308b94a60b006f36c437e7bf49`  
**Isolated private preview:** `/var/lib/samithai-editorial-staging-20261112`; verified HTTPS localhost `127.0.0.1:8843` with temporary self-signed certificate.

This ledger distinguishes source-code checks, real article reviews, and production acceptance. No automated test can prove image rights, factual accuracy, offsite backup restoration, or genuine iPhone/Android/tablet behavior.

## 1. Article and image review — pending human signoff

The six live article-image pairs were assigned and verified on the production database on 2026-11-12. Their draft status was verified then. The read-only fingerprint utility `support/samithai-production-six-draft-guard.php` must be executed again immediately before and after cutover, comparing `fingerprint_sha256`. It protects article body, draft state, image IDs and original image SHA-256; it never edits content.

| Article | Original image title | Source structural review | Specific remaining human review |
| --- | --- | --- | --- |
| 20 Practical AI Prompts for Small Businesses | `01_small-business-ai-prompts` | 1,181 words, 20 prompt examples; SEO title 56 chars / description 134 chars | Check each business template, factual claims, Khmer language and outgoing links; confirm image rights |
| How Teachers Can Use AI to Prepare Better Lesson Plans | `02_teachers-ai-lesson-plans` | 1,073 words; SEO 60 / 152 | Teacher review, student privacy, lesson accuracy, accessibility, local language and image rights |
| How to Create a Professional Website Using AI: From Brief to Launch | `03_ai-website-from-brief-to-launch` | 1,184 words; SEO 53 / 138 | Developer security/UX review, stage-vs-live claims, current links and image rights |
| 15 AI Prompts for Social Media Marketing That You Can Adapt | `04_social-media-ai-prompts` | 1,087 words; 15 templates; SEO 52 / 141 | Truthful advertising, no implied platform endorsement, placeholder hygiene and image rights |
| How to Test and Improve an AI Prompt: A Practical Quality Checklist | `05_ai-prompt-quality-checklist` | 1,239 words; SEO 54 / 135 | Match current review policy, avoid claiming model self-checks are independent verification, and image rights |
| AI Prompt Engineering vs. AI Agents: What’s the Difference? | `06_prompt-engineering-vs-ai-agents` | 1,340 words; SEO 52 / 135 | Agent tool access, product capability boundaries, linked intro article and image rights |

*The structural measures come from GitHub source JSON, not a new independent live editorial approval.* The user-selected images and content require human review against the currently deployed HTML. The source article links require working destination verification. Explicitly record reviewer, date, rights/license evidence, intended alt text, and whether changes were requested. The existing Editorial Workflow protects approval of the exact current draft with required accuracy, SEO, media, and rights acknowledgments. An automatic report or GitHub CI PASS must never set article status to approved.

Approval template per article: **Reviewer:** ___; **Reviewed date:** ___; **Content correctness:** ___; **SEO & links:** ___; **Image usage rights evidence:** ___; **Alt-text/visual fit:** ___; **Result:** changes requested / approved; **Notes:** ___.

Six articles must remain drafts until individual authorizations. The two introductory posts already published remain unchanged; 30 starter prompt products have their own review requirements.

## 2. Fresh production recovery protection — not yet executed

Current source contains `support/current-v3-encrypted-backup.sh`; that script is designed to snapshot live MySQL + private application state, validate source inventories and AES-256 ciphertext, and retain an SHA-256 manifest. It does **not** independently test restoration.

Recommended operator sequence with code provenance verified and passphrase never logged:

1. Run the existing pinned production backup utility in `--check` mode and compare its expected inventory version with the currently running release. If v3/v4 differ, stop and align with the live code before taking an operational snapshot.
2. Make a fresh backup using the verified utility `--snapshot`; the operator provides a secret passphrase via TTY. Protect the generated `.tar.gpg` and `.sha256`, and transfer a ciphertext copy to independently controlled storage.
3. Provision a separate, access-restricted **MySQL 8 server/instance** with distinct `@@server_uuid`, event scheduler disabled, and QA-only credentials stored as a root-owned mode-600 `my.cnf`. Do not reuse the production database or the same MySQL service with a different schema.
4. On an authorized server with access to the independent MySQL service, run `support/samithai-independent-restore-rehearsal.sh --check BACKUP QA_CNF`. Once checked, run `--restore`: it validates the encrypted archive and source manifests, restores into a new uniquely named QA schema, counts database rows, and streams/validates each original private media file checksum. **Real customer data enters the isolated QA instance; keep it non-public, with strict access controls and a retention/deletion plan.**
5. Record fresh snapshot SHA, QA server UUID comparison result, restored DB name, row-count and media integrity report, and an independent operator's acceptance. The QA DB is deliberately NOT auto-deleted so that investigators can inspect failures; its cleanup requires authorized deletion.

**Gate:** real independent restore completed and documented. GitHub's disposable restore tests cannot satisfy this gate.

## 3. Versioned staging candidate and cutover safety

The original preview candidate still runs `54c92ba0817b31673e81972c1d7beebe2bf9f17e`; subsequent fixes have been committed to PR #6. Before release:

1. Pin PR #6 HEAD and base SHA, run `support/samithai-staging-stage-latest.sh --check HEAD BASE`.
2. Use `--stage HEAD BASE` to create `releases/pr6-<SHA-prefix>` without changing the active `current` staging symlink, the already operational HTTPS listener, or any live data.
3. Inspect the new candidate's manifest, six-article synthetic review output, cached Blade compilation and route inventory. Do not silently hot-patch the active candidate.
4. At an authorized staging maintenance moment, stop its isolated HTTP and TLS services, switch the **staging-only** active symlink to that reviewed candidate with a verified rollback target, restart the latest private HTTPS launcher, and perform realistic browser acceptance. No production switch is part of this script.
5. For production, retain the old release and config. A protected shared-storage migration of private media, sessions, cache locks, logs, and installation lock must be completed under an approved maintenance window with a fresh backup and no concurrent writers. Compile Blade views per release. Run only additive migrations; do not run schema reset commands.
6. Before flipping production Nginx root, prove the new version preserves login sessions, works with shared private media and original image SHA hashes, and leaves six articles unpublished. Only after successful verified cutover, enable exactly one scheduler in aaPanel and verify no unapproved articles are queued.

**Gate:** production pointer, shared storage, Nginx, migrations and cron remain untouched until the acceptance requirements are explicitly met.

## 4. Trusted HTTPS, mobile, and real-browser acceptance — incomplete

The private TLS gateway at `https://127.0.0.1:8843` uses a **self-signed certificate**. A successful `curl -k`/localhost HTTP 200 proves basic reachability only. It does not establish trusted certificate validity, public DNS, or a usable link from a physical phone.

Before release, check trusted HTTPS without `-k` on the final intended hostname and inspect certificate hostname, expiry, chain, HSTS, redirects, and protected URL exposure; then record results from **physical** devices:

| Environment | Required checks | Status |
| --- | --- | --- |
| macOS Safari and Chromium | Login/logout, SEO forms, editorial queue, source-specific review notes, private previews, correct Open Graph fallback and noindex on drafts | Pending |
| Physical iPhone Safari | Responsive login, SEO Manager form, media preview, reviewer checklist, save feedback, session after navigation | Pending |
| Physical Android Chrome | Same as iPhone, including image fit and dark/light mode | Pending |
| Physical tablet (iPad/Android) | Sidebar, pagination, action menus, review controls, image previews at intermediate width | Pending |
| Public HTTPS after controlled cutover | Properly trusted certificate and fully qualified URLs; public articles remain unchanged; draft media not anonymously available; no sensitive URL exposure | Pending |

Use an authorized temporary preview hostname with trusted TLS or an explicitly approved private device tunnel for physical-device tests; localhost on the server is **not** directly accessible to those phones. Do not expose an unprotected test login or real customer data to public networks.

## Go/no-go statement

**NO-GO** until: six human editorial reviews and image licenses recorded; an independently restored fresh production backup verified; current GitHub candidate staged and manually accepted on browser/physical devices; shared storage and rollback rehearsed; HTTPS cert trusted; authorized maintenance approval recorded. Do not merge PR #6 or change the production vhost prematurely.
