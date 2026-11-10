#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-account-workspace-crud.sh
grep -Fq 'SOURCE_FILES=18_OF_18_VERIFIED' support/deploy-account-workspace-crud.sh
grep -Fq 'WORKSPACE_VIEW_DIRECTORY=CREATION_REQUIRED' support/deploy-account-workspace-crud.sh
grep -Fq 'install -d -o root -g www -m 0755 "$WORKSPACE_VIEW_DIR"' support/deploy-account-workspace-crud.sh
grep -Fq 'WORKSPACE_VIEW_DIRECTORY_ROLLBACK=REMOVED_EMPTY' support/deploy-account-workspace-crud.sh
grep -Fq 'BLOCKED=UNSAFE_INSTALL_PARENT:' support/deploy-account-workspace-crud.sh
python3 - <<'PY_SAFE_PARENT'
from pathlib import Path
s = Path('support/deploy-account-workspace-crud.sh').read_text()
backup = s.index('echo "MYSQL_BACKUP=VERIFIED"')
create = s.index('install -d -o root -g www -m 0755 "$WORKSPACE_VIEW_DIR"')
write = s.index('install -o root -g www -m 0644 "$WORK/$path" "$APP/$path"')
assert backup < create < write, "Directory must be created after backup, before first file write"
print('MISSING_WORKSPACE_DIRECTORY_INSTALL_ORDER=VERIFIED')
PY_SAFE_PARENT
grep -Fq 'ACCOUNT_WORKSPACE_CRUD_UPGRADE=SUCCESS' support/deploy-account-workspace-crud.sh
grep -Fq 'WORKSPACE_TASKS_SCHEMA=VERIFIED' support/deploy-account-workspace-crud.sh
grep -Fq 'APPLICATION_ROLLBACK=VERIFIED' support/deploy-account-workspace-crud.sh
grep -Fq 'MYSQL_BACKUP=VERIFIED' support/deploy-account-workspace-crud.sh
grep -Fq 'artisan migrate --path="$MIGRATION_FILE" --force' support/deploy-account-workspace-crud.sh
if grep -Eq 'migrate:(fresh|refresh|rollback)|db:wipe|DROP TABLE|artisan migrate:fresh' support/deploy-account-workspace-crud.sh; then
  echo 'Destructive database operation detected'
  exit 1
fi
if bash support/deploy-account-workspace-crud.sh --destroy >/dev/null 2>&1; then
  echo 'Unsupported deployment mode accepted'
  exit 1
fi
echo 'ACCOUNT_WORKSPACE_CRUD_DEPLOY_GATE=PASS'
