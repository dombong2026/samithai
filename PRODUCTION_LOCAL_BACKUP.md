# Restricted, operator-controlled production backup for samithai.com

Prerequisite: the production v2 inventory has been captured under /root/SamithAI-Upgrade-Evidence/pre-upgrade-inventory.json and verifies 13 tables, zero missing media and zero corrupt media. The actual MySQL account and database size remain private. Use a low-write maintenance window for a consistent backup. This utility performs no migration, deployment, remote transfer or restore.

From a pinned source commit run:

    bash support/production-local-backup.sh --check
    bash support/production-local-backup.sh --snapshot

The second command takes a single-transaction MySQL snapshot (rejecting non-InnoDB tables), including triggers/routines/events as permitted, creates a tarball of .env and storage/app, and compares a before-and-after v2 recovery inventory. It stores results under a root-only /root/SamithAI-Upgrade-Evidence/local-snapshot.* directory and writes an SHA256SUMS manifest. A failed run retains an INCOMPLETE sentinel. The transient MySQL credentials options file is deleted on exit. **It does not create a consistent cross-resource snapshot for every possible concurrently changed column; use a controlled maintenance window.**

### Confidentiality and scope

These snapshots include private customer content, password hashes, database records and APP_KEY. The local snapshot directory is root-only but the archives are **not yet encrypted**. Do not attach any backup, database credentials, environment secrets, or private inventories to chat, email or GitHub. Encrypt using a trusted key not stored with the backup, copy to independently held offsite storage, download the ciphertext back, validate checksums and restore into a fully separate MySQL database/private storage workspace.

The restore must run with **v2 code and the protected original APP_KEY** to compare to the original v2 inventory. Before using the feature upgrade's v3 command, install the additive review migration on that isolated clone and capture a new v3 baseline. Do not run migrations on production until offsite restore and rollback tests are accepted.
