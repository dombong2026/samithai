#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-workspace-header.sh
grep -Fq 'SOURCE_FILES=5_OF_5_VERIFIED' support/deploy-workspace-header.sh
grep -Fq 'WORKSPACE_HEADER_ROUTES=VERIFIED' support/deploy-workspace-header.sh
grep -Fq 'WORKSPACE_HEADER_UPGRADE=SUCCESS' support/deploy-workspace-header.sh
grep -Fq 'SOURCE_ROLLBACK=VERIFIED' support/deploy-workspace-header.sh
grep -Fq 'PINNED_SOURCE_BLOBS_AND_PHP_SYNTAX=VERIFIED' support/deploy-workspace-header.sh
if grep -Eq 'migrate:(fresh|refresh|rollback)|db:wipe|artisan migrate ' support/deploy-workspace-header.sh; then
 echo 'Unexpected DB migration';exit 1
fi
if bash support/deploy-workspace-header.sh --destroy >/dev/null 2>&1; then
 echo 'Unsupported deployment mode accepted';exit 1
fi
echo 'WORKSPACE_HEADER_DEPLOY_SYNTAX=PASS'
