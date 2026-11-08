#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-auth-eye-hotfix.sh
if bash support/deploy-auth-eye-hotfix.sh --invalid > /dev/null 2>&1; then
  echo 'Invalid deployment mode was unexpectedly accepted.'
  exit 1
fi
echo 'AUTH_UI_HOTFIX_SYNTAX=PASSED'
