#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/saved-insights-preflight.sh
if bash support/saved-insights-preflight.sh --apply >/dev/null 2>&1; then
  echo 'ERROR: read-only preflight accepted apply mode.'
  exit 1
fi
echo 'SAVED_INSIGHTS_PREFLIGHT_SYNTAX=PASS'
