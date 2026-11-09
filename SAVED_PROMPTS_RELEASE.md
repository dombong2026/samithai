# Samith AI — Saved Prompts production upgrade

Latest feature source: `8c7ea259d8d817bc9861929f8a1c24dc861f6f5f` (saved prompts, private creator insights, most-saved sorting, admin overview, integrity v4).

## Read-only preflight

After downloading `support/saved-insights-preflight.sh` from a pinned commit, run `bash -n` followed by `bash script --check`. This verifies the exact 16 existing source blobs, checks that the five new source files are absent, validates the already-installed prompt review migration and absence of the new prompt_saves table, and confirms a healthy current recovery inventory v3. No writes, migrations, backups or webserver changes are performed.

## Deployment gate

Previous user-verified AES-256 backup contains recovery inventory **v2**, captured *before* the prompt review upgrade. It does not protect prompt review records created since then. Capture a fresh encrypted, root-restricted MySQL + entire private storage + .env snapshot with source code v3 and validate its checksums before touching production. An off-site copy was explicitly declined, so its single-server failure risk remains, and that risk must be documented.

After backup acceptance, apply only `2026_11_09_020000_create_prompt_saves_table.php`, install the 16 updated files and five additions, compile the Blade views and routes, verify auth/saved creator insights on production, and capture a healthy v4 recovery inventory. Preserve the old application code as rollback source. Do not run `migrate:fresh`, alter the old licensed orders, enable paid checkout, or automatically restore older v2 SQL. A rollback after migration requires reviewing the additive schema before restoring compatible code.

CI passes do not substitute for real-device or host-level security and independent recovery acceptance.
