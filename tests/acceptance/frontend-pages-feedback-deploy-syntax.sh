#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-frontend-pages-and-feedback.sh
if bash support/deploy-frontend-pages-and-feedback.sh --destroy >/dev/null 2>&1; then
  echo 'Unexpected acceptance of unsupported deploy mode'
  exit 1
fi
grep -Fq 'SOURCE_FILES=16_OF_16_VERIFIED' support/deploy-frontend-pages-and-feedback.sh
grep -Fq 'SOURCE_STATE="ALREADY_UPDATED"' support/deploy-frontend-pages-and-feedback.sh
grep -Fq 'GLOBAL_SUCCESS_FEEDBACK_AUTO_DISMISS=INSTALLED' support/deploy-frontend-pages-and-feedback.sh
if grep -Eq 'migrate:(fresh|refresh|rollback)|db:wipe|artisan migrate ' support/deploy-frontend-pages-and-feedback.sh; then
 echo 'Unexpected database migration in source-only release'
 exit 1
fi
echo 'FRONTEND_PAGES_RELEASE_SCRIPT_SYNTAX=PASS'
