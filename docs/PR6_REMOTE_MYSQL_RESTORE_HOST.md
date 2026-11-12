# Samith AI PR #6 — Isolated MySQL 8 restore host setup

**Scope:** a **different VM/server** running MySQL 8, not the aaPanel production MySQL service, and not a schema added to the production service. The independently encrypted v4 backup is already saved on the production host; do not copy its plaintext or production credentials.

## Host and access requirements

1. Provision or select an **authorized, dedicated QA server**. Restrict network access via host firewall/security group. Install a supported, maintained MySQL 8 server. Pin supported versions and packages through the operator's normal OS management process; do not run arbitrary remote installation scripts.
2. Configure MySQL to listen only on `127.0.0.1:3306`. Ensure `@@global.event_scheduler` is `OFF` or `DISABLED`. Disable outbound network access for restored data where practicable. No web app/cron/mail/payment services may run from this recovery clone.
3. On **that QA server**, verify the connection as a DBA:
   ```bash
   mysql --protocol=TCP -h 127.0.0.1 -P 3306 -u QA_OPERATOR -p \
      -NBe 'SELECT @@version,@@server_uuid,@@event_scheduler'
   ```
   Create an approved disposable QA restore account with permission to create a **new QA schema** and import its tables, triggers, views/routines/events as necessary. The restore tool does not delete a QA database after testing. Protect real production customer data under the organization's privacy and retention requirements.
4. Establish **SSH host key trust** between the production aaPanel operator and the independent QA server. From a dedicated shell on the **production** server, open a loopback-only forward. Substitute the known QA SSH account and hostname after verifying the host key independently:
   ```bash
   ssh -N -o ExitOnForwardFailure=yes \
      -L 127.0.0.1:33079:127.0.0.1:3306 \
      QA_SSH_ACCOUNT@QA_SERVER
   ```
   Do **not** expose QA MySQL on a public interface; do not bypass SSH host-key checks.
5. In a **second shell on the production server**, run the pinned `support/samithai-pr6-remote-qa-tunnel.sh --probe`, then `--configure`. The latter prompts privately for the **QA-only** MySQL username and password and creates root-only `/root/SamithAI-PR6-Release-Readiness/qa-remote-ssh-tunnel.cnf`. Its preflight calls the existing restore operator's `--check`; an independent `@@server_uuid` and disabled event scheduler are mandatory. No backup decrypt or QA database write happens yet.
6. When `DISTINCT_QA_MYSQL_INSTANCE=PASS`, record the independent host authorization, confirm backup retention policy and run:
   ```bash
   bash /root/SamithAI-PR6-Release-Readiness/versioned-independent-restore.sh \
     --restore \
     /root/SamithAI-Upgrade-Evidence/samithai-recovery-v4-20261112T165749Z.tar.gpg \
     /root/SamithAI-PR6-Release-Readiness/qa-remote-ssh-tunnel.cnf
   ```
   The backup passphrase is requested privately via terminal. Do not paste it into chats or logs. The restore validates checksums, exact table counts and private media SHA-256. **It is not a full application-login or disaster-recovery test**, and an independent operator must also verify business-critical records and actual application recovery.
7. Restrict and eventually purge the QA restored schema, QA backup copy, and QA credentials under an approved data-retention policy after evidence is captured. Preserve the encrypted backup plus manifest in an independently managed, access-controlled location. Do not assume storing the only backup on the production server protects against host failure.

## Failure rules

- If the remote host is unavailable, SSH tunnel is not up, the client file is missing, or `@@server_uuid` equals production, stop. Do not create a second database on the live service.
- If import fails due to MySQL permissions/definers, do not relax production MySQL privileges. Reconcile the **independent** QA restore account or data import strategy with the DBA.
- Never expose or forward `.env`, real usernames/passwords, private media, or raw customer data in reports.

**Release status:** independent restore remains pending until the isolated QA preflight and recovery test pass, all other editorial/device acceptance gates remain outstanding, and PR #6 stays draft.
