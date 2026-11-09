# Samith AI — reviewed prompt moderation and sorting production upgrade

## Operator choice

The operator explicitly requested proceeding with the new features without an offsite backup, acknowledging that the current platform is incomplete. The previously verified root-restricted AES-256 encrypted backup and local v2 recovery inventory remain the only recovery copy known to the upgrade. Losing this server or its disks may mean unrecoverable customer/site data; source CI does not eliminate that risk.

## Scope

This upgrade modifies exactly nine tracked application/controller/view/CSS files and adds three new files, including one additive migration creating `prompt_reviews`. It does not change the user's `.env`, Nginx, media, existing accounts/orders/entitlements, or payments. Paid checkout stays disabled.

## Operations

Download the `support/deploy-feature-upgrade.sh` at a pinned GitHub commit, run `bash -n`, then run `--check`, and only if that passes, `--apply`. The script checks SHA256 of the encrypted backup, the corresponding verified source archive manifest and original v2 recovery inventory; refuses any drifted production file; downloads a pinned 12-file patch and verifies each Git blob hash. After copying the current code into a protected `/root` backup it briefly enters Laravel maintenance, installs files, runs **only** `2026_11_09_000100_create_prompt_reviews.php` with `migrate --path`, compiles views, captures a healthy recovery inventory v3, exits maintenance and checks origin HTML and CSS.

If deployment fails, the script attempts to restore the prior code and exit maintenance mode. It deliberately does not drop/rollback a newly created database table or migration record. If a failure occurs after migration, the schema may remain and an operator must inspect it before retrying. Do not run migrate:fresh, db:wipe, migrate:refresh or restore production SQL automatically.

Note: source-code and origin HTTP tests cannot prove real iPhone/Android UI, external SMTP, payment eligibility, backups on another server, legal compliance or a customer production release. The local backup should be transferred offsite when practical.
