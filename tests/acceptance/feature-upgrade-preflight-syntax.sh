#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/production-feature-upgrade-preflight.sh
if bash support/production-feature-upgrade-preflight.sh --apply >/dev/null 2>&1; then
  echo 'ERROR: a read-only audit accepted an apply mode'
  exit 1
fi
echo 'READ_ONLY_PRODUCTION_UPGRADE_CHECK_SYNTAX=PASS'
