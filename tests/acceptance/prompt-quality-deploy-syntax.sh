#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-prompt-quality-center.sh
grep -Fq 'SOURCE_FILES=16_OF_16_VERIFIED' support/deploy-prompt-quality-center.sh
grep -Fq 'PROMPT_QUALITY_CENTER_UPGRADE=SUCCESS' support/deploy-prompt-quality-center.sh
grep -Fq 'PROMPT_QUALITY_SCHEMA=VERIFIED' support/deploy-prompt-quality-center.sh
grep -Fq 'PROMPT_QUALITY_ROUTES=VERIFIED' support/deploy-prompt-quality-center.sh
grep -Fq 'APPLICATION_ROLLBACK=VERIFIED' support/deploy-prompt-quality-center.sh
grep -Fq 'MYSQL_BACKUP=VERIFIED' support/deploy-prompt-quality-center.sh
grep -Fq 'artisan migrate --path="$MIGRATION_FILE" --force' support/deploy-prompt-quality-center.sh
grep -Fq 'STARTER_PROMPT_SUBMISSION_GATE=ENFORCED' support/deploy-prompt-quality-center.sh
if grep -Eq 'migrate:(fresh|refresh|rollback)|db:wipe|DROP TABLE|artisan migrate:fresh' support/deploy-prompt-quality-center.sh; then
  echo 'BLOCKED=DESTRUCTIVE_DATABASE_OPERATION'; exit 1
fi
if bash support/deploy-prompt-quality-center.sh --invalid >/dev/null 2>&1; then
  echo 'BLOCKED=UNSUPPORTED_DEPLOYMENT_MODE'; exit 1
fi
python3 - <<'PY_GATE'
from pathlib import Path
s=Path('support/deploy-prompt-quality-center.sh').read_text()
a=s.index('echo "MYSQL_BACKUP=VERIFIED"')
b=s.index('install -o root -g www -m 0644 "$WORK/$path" "$APP/$path"')
c=s.index('artisan migrate --path="$MIGRATION_FILE" --force')
assert a<b<c,'Backup must be verified before writes or migration'
assert 'BLOCKED=UNSAFE_INSTALL_PARENT' in s
print('PROMPT_QUALITY_RELEASE_GATES=PASS')
PY_GATE
