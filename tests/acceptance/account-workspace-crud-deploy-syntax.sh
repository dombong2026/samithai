#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-account-workspace-crud.sh
grep -Fq 'SOURCE_FILES=18_OF_18_VERIFIED' support/deploy-account-workspace-crud.sh
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
