#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-account-creator-navigation.sh
if bash support/deploy-account-creator-navigation.sh --unsafe >/dev/null 2>&1; then
  echo "ERROR: unknown deploy operation accepted"
  exit 1
fi
grep -Fq 'SOURCE_FILES=3_OF_3_VERIFIED' support/deploy-account-creator-navigation.sh
grep -Fq 'STATE="ALREADY_UPDATED"' support/deploy-account-creator-navigation.sh
grep -Fq 'ACCOUNT & CREATOR UI UPGRADE SUCCESS' support/deploy-account-creator-navigation.sh
if grep -Eq 'migrate:(fresh|refresh|rollback)|db:wipe|artisan migrate --' support/deploy-account-creator-navigation.sh; then
  echo "ERROR: destructive database operation detected"
  exit 1
fi
echo "ACCOUNT_CREATOR_UI_DEPLOYMENT_SYNTAX=PASS"
