# Saved Prompts + Creator Insights — guarded aaPanel upgrade

Target production installation: `samithai.com`, currently confirmed with prompt-review table, v3 recovery inventory and exact 16-file baseline. The operator has verified a fresh local AES-256 encrypted backup at `/root/SamithAI-Upgrade-Evidence/samithai-current-v3-20261109T062845Z.tar.gpg`, its matching SHA256 file and successful decryption before this upgrade. Offsite transfer and restoration remain unverified by explicit operator choice.

## Scoped deployment

Run `support/deploy-saved-insights.sh --check` first. A successful check only reads source hashes, checks MySQL state, encrypted backup SHA256 and current v3 recovery inventory. `--apply` independently re-runs those checks, verifies GitHub-sha-pinned files, creates a root-only backup of existing code, briefly enables maintenance, updates 16 existing source files and adds 5 new files, runs **only** `2026_11_09_020000_create_prompt_saves_table.php`, rebuilds routes/Blade, verifies v4 recovery inventory with 15 tables, exits maintenance and performs live-origin guest and asset smoke checks.

## Safety limits

- No changes to `.env`, the running Nginx configuration, existing media, licenses, orders or payment provider settings. Paid checkout remains disabled.
- The previous v3 encrypted SQL backup is never unencrypted or restored by this script. Do not expose the decryption passphrase.
- On an error after source installation the script **attempts code rollback and exits maintenance mode**. It does NOT automatically delete a newly created prompt_saves table or migration record; this must be evaluated before retrying. The new table is additive and foreign-key scoped to real user/prompt records.
- Authenticated creator and customer smoke tests are in CI; the live script can verify guest redirects only. Authenticated live-role and mobile acceptance still require a user session.
- Keeping all recovery artifacts on the same server retains server-loss risk. Offsite recovery and independent restoration remain unverified.

## Post-upgrade

Use `/account/saved`, `/account/creator/insights`, `/prompts?sort=most_saved`, and `/dashboard` with authorized accounts. The script captures a root-restricted `post-upgrade-v4-inventory.json` in its code-backup directory. Retain it, plus the encrypted v3 backup and confidential passphrase stored independently.
