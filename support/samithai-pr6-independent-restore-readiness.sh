#!/usr/bin/env bash
# Read-only readiness for the exact production v4 encrypted snapshot.
# Never creates schemas, decrypts backup data, or edits Nginx/cron/production.
set -Eeuo pipefail
set +x
umask 077

if [[ $# -lt 1 || $# -gt 2 || "$1" != --audit ]]; then
    echo "Usage: script.sh --audit [/root/authorized-independent-qa.cnf]" >&2
    exit 2
fi

BACKUP="/root/SamithAI-Upgrade-Evidence/samithai-recovery-v4-20261112T165749Z.tar.gpg"
CHECKSUM="$BACKUP.sha256"
DIR="/root/SamithAI-PR6-Release-Readiness"
RESTORE="$DIR/versioned-independent-restore.sh"
EXPECTED_RESTORE_BLOB="52c5060aa4d87ad0ec5ff7ec281a56cd129c3462"
PROD="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
QACNF=""
if [[ $# -eq 2 ]]; then QACNF="$2"; fi

fail() {
    echo "BLOCKED=$*" >&2
    echo "ENCRYPTED_BACKUP_AND_PRODUCTION=PRESERVED"
    exit 3
}

echo "========== PROTECT VERIFIED PRODUCTION SNAPSHOT =========="
[[ "$(id -u)" == 0 ]] || fail "Root required"
for tool in sha256sum git stat bash; do
    command -v "$tool" >/dev/null || fail "Missing $tool"
done
[[ -f "$BACKUP" && ! -L "$BACKUP" &&
   -f "$CHECKSUM" && ! -L "$CHECKSUM" &&
   -f "$RESTORE" && ! -L "$RESTORE" &&
   -d "$DIR" && ! -L "$DIR" &&
   -f "$PROD/artisan" ]] ||
    fail "Pinned backup, checksum, restore operator or production identity missing"
[[ "$(stat -c '%u' "$BACKUP")" == 0 &&
   "$(stat -c '%u' "$CHECKSUM")" == 0 &&
   "$(stat -c '%u' "$RESTORE")" == 0 ]] ||
    fail "A protected source is not owned by root"
[[ "$(stat -c '%a' "$BACKUP")" == 600 &&
   "$(stat -c '%a' "$CHECKSUM")" == 600 ]] ||
    fail "Backup and checksum must be mode 600"
[[ "$(git hash-object "$RESTORE")" == "$EXPECTED_RESTORE_BLOB" ]] ||
    fail "Independent restore operator hash mismatch"
bash -n "$RESTORE" || fail "Independent restore script syntax"
(
    cd "$(dirname "$BACKUP")"
    sha256sum --status -c "$(basename "$CHECKSUM")"
) || fail "Backup SHA256 mismatch"
echo "PROTECTED_BACKUP_SHA256=PASS"
echo "BACKUP_INVENTORY=v4"
echo "BACKUP_ARTIFACT=$(basename "$BACKUP")"
echo "RESTORE_OPERATOR_INTEGRITY=PASS"

if [[ -z "$QACNF" ]]; then
    echo "INDEPENDENT_QA_CREDENTIALS=NOT_SUPPLIED"
    echo "INDEPENDENT_RESTORE=NOT_STARTED"
    echo "NEXT=Provision an isolated MySQL 8 instance with separate server UUID and event_scheduler OFF; keep QA credentials in a root-owned 0600 options file"
    echo "PRODUCTION_MYSQL_NGINX_CRON=UNCHANGED"
    exit 0
fi

echo "========== VERIFY OPERATOR-SELECTED QA DATABASE INSTANCE =========="
[[ "$QACNF" == /* && -f "$QACNF" && ! -L "$QACNF" &&
   "$(stat -c '%u' "$QACNF")" == 0 &&
   "$(stat -c '%a' "$QACNF")" == 600 ]] ||
    fail "Independent QA MySQL options file must be an absolute root-owned mode 600 file"
# The pinned restore utility refuses to proceed unless production and QA
# MySQL @@server_uuid differ and QA event_scheduler is OFF/DISABLED.
bash "$RESTORE" --check "$BACKUP" "$QACNF" ||
    fail "Independent QA instance isolation verification failed"
echo "INDEPENDENT_RESTORE_PREFLIGHT=PASS"
echo "RESTORE_EXECUTION=NOT_PERFORMED"
echo "OFFSITE_CIPHERTEXT_COPY=NOT_VERIFIED"
echo "PRODUCTION_MYSQL_NGINX_CRON=UNCHANGED"
