#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-login-recovery-row.sh
if bash support/deploy-login-recovery-row.sh --invalid >/dev/null 2>&1; then
  echo 'ERROR: unsupported deployment mode was accepted.'
  exit 1
fi
echo 'LOGIN_ROW_DEPLOYMENT_SYNTAX=PASS'
