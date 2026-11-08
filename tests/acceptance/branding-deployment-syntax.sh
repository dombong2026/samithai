#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-branding.sh
if bash support/deploy-branding.sh --invalid >/dev/null 2>&1; then
  echo 'ERROR: invalid deployment mode accepted'
  exit 1
fi
echo 'BRANDING_DEPLOY_SCRIPT_SYNTAX=PASSED'
