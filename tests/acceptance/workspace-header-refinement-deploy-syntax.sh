#!/usr/bin/env bash
set -Eeuo pipefail

bash -n support/deploy-workspace-header-refinement.sh

grep -Fq 'SOURCE_FILES=3_OF_3_VERIFIED' support/deploy-workspace-header-refinement.sh
grep -Fq 'WORKSPACE_HEADER_REFINEMENT=SUCCESS' support/deploy-workspace-header-refinement.sh
grep -Fq 'LANGUAGE_FLAGS_13_LOCALES=INSTALLED' support/deploy-workspace-header-refinement.sh
grep -Fq 'STRAY_HEADER_CHARACTER=REMOVED' support/deploy-workspace-header-refinement.sh
grep -Fq 'SOURCE_ROLLBACK=VERIFIED' support/deploy-workspace-header-refinement.sh
grep -Fq 'PINNED_SOURCE_BLOBS_AND_PHP_SYNTAX=VERIFIED' support/deploy-workspace-header-refinement.sh

if grep -Eq 'migrate:(fresh|refresh|rollback)|db:wipe|artisan migrate ' support/deploy-workspace-header-refinement.sh; then
  echo 'Database mutation is not allowed for header-only deployment'
  exit 1
fi
if bash support/deploy-workspace-header-refinement.sh --destroy >/dev/null 2>&1; then
  echo 'Unsupported deployment mode was accepted'
  exit 1
fi

echo 'WORKSPACE_HEADER_REFINEMENT_DEPLOY_SYNTAX=PASS'
