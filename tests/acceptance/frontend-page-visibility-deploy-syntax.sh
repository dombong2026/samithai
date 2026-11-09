#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-frontend-page-visibility.sh
grep -Fq 'SOURCE_FILES=16_OF_16_VERIFIED' support/deploy-frontend-page-visibility.sh
grep -Fq 'FRONTEND_PAGE_VISIBILITY_UPGRADE=SUCCESS' support/deploy-frontend-page-visibility.sh
grep -Fq 'SOURCE_ROLLBACK=VERIFIED' support/deploy-frontend-page-visibility.sh
grep -Fq 'PINNED_SOURCE_BLOBS_AND_PHP_SYNTAX=VERIFIED' support/deploy-frontend-page-visibility.sh
if grep -Eq 'migrate:(fresh|refresh|rollback)|db:wipe|artisan migrate ' support/deploy-frontend-page-visibility.sh; then
  echo 'Unexpected database migration in source-only release'
  exit 1
fi
if bash support/deploy-frontend-page-visibility.sh --destroy >/dev/null 2>&1; then
  echo 'Unsupported mode must be rejected'
  exit 1
fi
echo 'FRONTEND_PAGE_VISIBILITY_RELEASE_SCRIPT=PASS'
