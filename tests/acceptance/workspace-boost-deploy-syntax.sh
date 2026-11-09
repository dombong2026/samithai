#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-workspace-boost.sh
grep -Fq 'SOURCE_FILES=14_OF_14_VERIFIED' support/deploy-workspace-boost.sh
grep -Fq 'WORKSPACE_BOOST_UPGRADE=SUCCESS' support/deploy-workspace-boost.sh
grep -Fq 'WORKSPACE_PREFERENCES_SCHEMA=VERIFIED' support/deploy-workspace-boost.sh
grep -Fq 'APPLICATION_ROLLBACK=VERIFIED' support/deploy-workspace-boost.sh
grep -Fq 'MYSQL_BACKUP=VERIFIED' support/deploy-workspace-boost.sh
grep -Fq 'artisan migrate --path="$MIGRATION_FILE" --force' support/deploy-workspace-boost.sh
if grep -Eq 'migrate:(fresh|refresh|rollback)|db:wipe|DROP TABLE|artisan migrate:fresh' support/deploy-workspace-boost.sh; then
  echo 'Unsafe/destructive database operation detected'
  exit 1
fi
if bash support/deploy-workspace-boost.sh --invalid >/dev/null 2>&1; then
  echo 'Unsupported mode accepted'
  exit 1
fi
echo 'WORKSPACE_BOOST_RELEASE_GATE=PASS'
