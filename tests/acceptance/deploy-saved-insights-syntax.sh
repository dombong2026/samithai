#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-saved-insights.sh
if bash support/deploy-saved-insights.sh --unsafe >/dev/null 2>&1; then
  echo "ERROR: unsupported production upgrade mode accepted"
  exit 1
fi
if grep -Eq 'migrate:(fresh|refresh|rollback)|db:wipe' support/deploy-saved-insights.sh; then
  echo "ERROR: destructive migration command found in upgrade"
  exit 1
fi
grep -Fq -- '--path=database/migrations/2026_11_09_020000_create_prompt_saves_table.php' support/deploy-saved-insights.sh
echo "SAVED_INSIGHTS_PRODUCTION_UPGRADE_SYNTAX=PASS"
