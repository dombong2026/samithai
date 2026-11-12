#!/usr/bin/env bash
# Static release-preparation gate: never touches live sites or secrets.
set -Eeuo pipefail
for script in \
  support/samithai-staging-stage-latest.sh \
  support/samithai-staging-inherited-lock-recover.sh \
  support/samithai-staging-activate-reviewed.sh \
  support/samithai-independent-restore-rehearsal.sh \
  support/samithai-versioned-encrypted-backup.sh \
  support/samithai-versioned-independent-restore.sh \
  support/samithai-staging-https.sh; do
  test -s "$script" || { echo "MISSING=$script"; exit 1; }
  bash -n "$script"
done
test -s support/samithai-staging-post-stop-reconcile.py
python3 - <<'PY'
import ast
from pathlib import Path
s=Path('support/samithai-staging-post-stop-reconcile.py').read_text()
ast.parse(s)
required=[
    "HTTPS_FLOCK_STILL_HELD_AFTER_VERIFIED_CHILD_SHUTDOWN",
    "LOCK_FD_HELD_BY_UNRELATED_OR_UNVERIFIED_PROCESS",
    "READY_FOR_EXISTING_OFFLINE_ACTIVATE_SCRIPT=YES",
    "STAGING_PORTS_ARE_NOT_BOTH_FREE",
    "ORIGINAL_STAGING_POINTER_MUST_REMAIN_ACTIVE",
]
assert all(item in s for item in required)
assert 'os.unlink(HTTPS_LOCK)' not in s
assert 'os.remove(HTTPS_LOCK)' not in s
print('POST_STOP_FLOCK_RECONCILE_STATIC=PASS')
PY
test -s support/samithai-production-six-draft-guard.php
php -l support/samithai-production-six-draft-guard.php >/dev/null

grep -Fq 'NEW_RELEASE=NOT_YET_CREATED' support/samithai-staging-stage-latest.sh
grep -Fq 'STAGING_POINTER=UNCHANGED' support/samithai-staging-stage-latest.sh
grep -Fq 'STAGING_HTTPS_OPERATOR_FLOCK=RELEASED' support/samithai-staging-inherited-lock-recover.sh
grep -Fq 'HTTP_LISTENER_NOT_IN_STAGING_PROCESS_GROUP' support/samithai-staging-inherited-lock-recover.sh
grep -Fq 'HTTPS_LOCK_HELD_BY_UNEXPECTED_PROCESS' support/samithai-staging-inherited-lock-recover.sh
grep -Fq 'STAGING_ACTIVE_POINTER=OLD_CANDIDATE_UNCHANGED' support/samithai-staging-inherited-lock-recover.sh
grep -Fq ') 9>&- > "$LAB/http-server.log"' support/samithai-staging-https.sh
grep -Fq 'python3 "$LAB/tls-proxy.py" 9>&-' support/samithai-staging-https.sh
grep -Fq 'STAGING_POINTER_ROLLED_BACK=OLD_CANDIDATE' support/samithai-staging-activate-reviewed.sh
grep -Fq 'samithai/pr6-staged/v1' support/samithai-staging-https.sh
grep -Fq 'Reviewed release name does not match commit SHA' support/samithai-staging-https.sh
if grep -Fq '[[ "$MODE" == --candidate || "$MODE" == --rollback ]]' support/samithai-staging-https.sh; then
    echo "BLOCKED=UNREVIEWED_STAGING_POINTER_SWITCH"
    exit 1
fi
grep -Fq 'DISTINCT' support/samithai-independent-restore-rehearsal.sh
grep -Fq 'PRODUCTION_MYSQL_AND_MEDIA=UNCHANGED' support/samithai-independent-restore-rehearsal.sh
grep -Fq 'LIVE_PRODUCTION_INVENTORY=' support/samithai-versioned-encrypted-backup.sh
grep -Fq 'samith-recovery-inventory/v2' support/samithai-versioned-encrypted-backup.sh
grep -Fq 'samith-recovery-inventory/v3' support/samithai-versioned-encrypted-backup.sh
grep -Fq 'samith-recovery-inventory/v4' support/samithai-versioned-encrypted-backup.sh
grep -Fq 'LIVE_INVENTORY_BEFORE_AFTER=IDENTICAL' support/samithai-versioned-encrypted-backup.sh
grep -Fq 'INDEPENDENT_RESTORED_EXACT_ROW_COUNTS=PASS' support/samithai-versioned-independent-restore.sh
grep -Fq 'QA_MYSQL_DIFFERENT_SERVER_UUID=PASS' support/samithai-versioned-independent-restore.sh
grep -Fq 'SQL_DUMP_SCHEMA_SELECTION=REJECTED' support/samithai-versioned-independent-restore.sh
grep -Fq 'REAL_PRIVATE_MEDIA_CHECKSUMS=PASS' support/samithai-versioned-independent-restore.sh
grep -Fq 'READ_ONLY' support/samithai-production-six-draft-guard.php
grep -Fq 'TLS_SOURCE="$CURRENT/support/samithai-staging-tls-proxy.py"' \
    support/samithai-staging-https.sh

if grep -Eq 'migrate:(fresh|refresh)|db:wipe|DROP DATABASE|TRUNCATE TABLE' \
   support/samithai-staging-stage-latest.sh \
   support/samithai-production-six-draft-guard.php; then
    echo "BLOCKED=RELEASE_RESET_OPERATION"
    exit 1
fi
echo "SAMITHAI_PRODUCTION_RELEASE_PREPARATION_STATIC=PASS"
