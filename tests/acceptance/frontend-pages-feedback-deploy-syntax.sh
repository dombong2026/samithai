#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-frontend-pages-and-feedback.sh
if bash support/deploy-frontend-pages-and-feedback.sh --destroy >/dev/null 2>&1; then
  echo 'Unexpected acceptance of unsupported deploy mode'
  exit 1
fi
grep -Fq 'SOURCE_FILES=21_OF_21_VERIFIED' support/deploy-frontend-pages-and-feedback.sh
grep -Fq 'SOURCE_STATE="ALREADY_UPDATED"' support/deploy-frontend-pages-and-feedback.sh
grep -Fq 'GLOBAL_SUCCESS_FEEDBACK_AUTO_DISMISS=INSTALLED' support/deploy-frontend-pages-and-feedback.sh
grep -Fq 'route:list --except-vendor --json' support/deploy-frontend-pages-and-feedback.sh
grep -Fq 'OLD_SOURCE_ROLLBACK=VERIFIED' support/deploy-frontend-pages-and-feedback.sh
grep -Fq 'MAINTENANCE_EXIT=VERIFIED' support/deploy-frontend-pages-and-feedback.sh
if grep -Eq 'migrate:(fresh|refresh|rollback)|db:wipe|artisan migrate ' support/deploy-frontend-pages-and-feedback.sh; then
 echo 'Unexpected database migration in source-only release'
 exit 1
fi
bash tests/acceptance/frontend-page-visibility-deploy-syntax.sh
bash tests/acceptance/workspace-header-deploy-syntax.sh
bash tests/acceptance/workspace-header-refinement-deploy-syntax.sh
bash tests/acceptance/workspace-boost-deploy-syntax.sh
bash tests/acceptance/account-workspace-crud-deploy-syntax.sh
bash tests/acceptance/all-frontend-pages-deploy-syntax.sh
bash tests/acceptance/starter30-content-deploy-syntax.sh
bash tests/acceptance/prompt-quality-deploy-syntax.sh
echo 'FRONTEND_PAGES_RELEASE_SCRIPT_SYNTAX=PASS'
