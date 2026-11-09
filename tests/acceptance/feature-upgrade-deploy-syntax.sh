#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-feature-upgrade.sh
if bash support/deploy-feature-upgrade.sh --destroy >/dev/null 2>&1; then
  echo 'Unexpected acceptance of unsafe upgrade mode'
  exit 1
fi
echo 'SCOPED_FEATURE_UPGRADE_SCRIPT_SYNTAX=PASS'
