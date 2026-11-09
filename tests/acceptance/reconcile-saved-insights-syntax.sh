#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/reconcile-saved-insights.sh
if bash support/reconcile-saved-insights.sh --apply >/dev/null 2>&1; then
  echo "ERROR: unsupported mode accepted"
  exit 1
fi
grep -Fq 'INSTALLED_NEW_RELEASE_FILES=21_OF_21_MATCHED' support/reconcile-saved-insights.sh
grep -Fq -- '--path=database/migrations/2026_11_09_020000_create_prompt_saves_table.php' support/reconcile-saved-insights.sh
if grep -Eq 'migrate:(fresh|refresh|rollback)|db:wipe' support/reconcile-saved-insights.sh; then
  echo "ERROR: destructive migration operation present"
  exit 1
fi
echo "SAVED_INSIGHTS_RECONCILIATION_SYNTAX=PASS"
