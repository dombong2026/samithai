# Prompt moderation and creator review feedback

Samith AI already has creator drafts, review submission, administrator moderation, and protected customer entitlements. This milestone closes an important workflow gap: prior rejection did not supply a written explanation or a review timeline.

## Implemented

- Admin rejection of a pending prompt requires 10–1,200 characters of written feedback.
- An approval may proceed without a note.
- The system records submitted, approved and rejected events with actor, timestamp and optional note. The prompt state and event are written in a single database transaction.
- Creators can view reviewer feedback on their own draft or rejected prompts, and browse a private paginated review history.
- Only the creator owning the prompt and administrators can open the history; public visitors and unrelated creators cannot.
- Creators can revise and resubmit rejected prompts; earlier feedback remains accessible in the timeline.
- Review notes are plain text, escaped in HTML. No email service or external provider is necessary.

## Database migration

The additive 2026_11_09_000100_create_prompt_reviews.php migration creates the prompt_reviews table with prompt and actor references, action, note, and timestamps. Old prompts have no backfilled review events. Deletion of a prompt without customer orders cascades to its review records; the independent audit_events table is not changed.

## Production safety

This feature is committed to GitHub main, not automatically deployed. A live installation requires the additive migration and updated source/templates. Take and verify an off-host production backup before migrating. Do not run migrate:fresh or overwrite .env. Paid checkout remains disabled.

Outstanding: real payment onboarding, SMTP, full translations, legal counsel, external backup restoration, physical-device testing and independent hosting acceptance.

Review records are included in the operator recovery inventory v3; a review-note content change invalidates the keyed digest without exposing note text.
