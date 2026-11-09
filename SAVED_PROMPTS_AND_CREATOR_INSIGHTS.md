# Samith AI — Saved Prompts, Discovery and Creator Insights

This milestone adds real user features to the existing Laravel marketplace, without a new payment provider, external queue or mock metrics.

## Customer

- Save/unsave a published prompt using a compact heart control in product cards and a labeled button on its detail page.
- View a private `/account/saved` searchable page with only currently published saved prompts.
- Save and remove operations are CSRF-protected, authenticated, rate-limited and idempotent. Saving does NOT grant a license, unlock paid content or create an order.
- Unpublished/removed prompts cannot be saved or surfaced in public or saved discovery; previously saved but unpublished prompts can be forgotten via the authenticated DELETE route.

## Discovery

- `Most saved` sorting on `/prompts` uses real saved-user counts with stable publication/id tie-breakers.
- Guest cards offer sign-in before saving; authenticated card controls show real per-viewer status using a correlated `withExists` query instead of N+1 save checks.

## Creator

- Private `/account/creator/insights` page shows counts of own draft/pending/rejected/published prompts, save interest on own published prompts, and genuine entitlement claim counts.
- Top ten published listings are ranked by real saves. Creators cannot view another creator's private insight page or private prompt source.
- No invented revenue, fake star ratings, view tracking, or payment activity is presented.

## Schema, safety and release

- One additive migration `2026_11_09_020000_create_prompt_saves_table.php`, composite user/prompt uniqueness, foreign-key cascades and a prompt-side lookup index.
- Recovery inventory changes from v3 to v4 and includes keyed fingerprints of saved relationships. A new v4 inventory must be captured after migration; older v3 comparisons intentionally reject incompatible versions.
- The production site currently has the previous moderation/sorting feature upgrade. THIS GitHub milestone does not deploy or migrate production automatically.
- Before production deployment, capture a fresh consistent and encrypted **v3** baseline because the previous encrypted local backup is from **v2** and predates `prompt_reviews`. Do not restore it over current production unless intentionally reverting to that older state.
- Paid checkout, verified provider callbacks, refunds, subscriptions, localization, offsite recovery and physical-device tests remain separate release gates.
