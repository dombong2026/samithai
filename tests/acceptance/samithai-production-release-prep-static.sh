#!/usr/bin/env bash
# Static release-preparation gate: never touches live sites or secrets.
set -Eeuo pipefail
for script in \
  support/samithai-staging-stage-latest.sh \
  support/samithai-staging-activate-reviewed.sh \
  support/samithai-independent-restore-rehearsal.sh \
  support/samithai-staging-https.sh; do
  test -s "$script" || { echo "MISSING=$script"; exit 1; }
  bash -n "$script"
done
test -s support/samithai-production-six-draft-guard.php
php -l support/samithai-production-six-draft-guard.php >/dev/null

grep -Fq 'NEW_RELEASE=NOT_YET_CREATED' support/samithai-staging-stage-latest.sh
grep -Fq 'STAGING_POINTER=UNCHANGED' support/samithai-staging-stage-latest.sh
grep -Fq 'STAGING_POINTER_ROLLED_BACK=OLD_CANDIDATE' support/samithai-staging-activate-reviewed.sh
grep -Fq 'samithai/pr6-staged/v1' support/samithai-staging-https.sh
grep -Fq 'Reviewed release name does not match commit SHA' support/samithai-staging-https.sh
if grep -Fq '[[ "$MODE" == --candidate || "$MODE" == --rollback ]]' support/samithai-staging-https.sh; then
    echo "BLOCKED=UNREVIEWED_STAGING_POINTER_SWITCH"
    exit 1
fi
grep -Fq 'DISTINCT' support/samithai-independent-restore-rehearsal.sh
grep -Fq 'PRODUCTION_MYSQL_AND_MEDIA=UNCHANGED' support/samithai-independent-restore-rehearsal.sh
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
