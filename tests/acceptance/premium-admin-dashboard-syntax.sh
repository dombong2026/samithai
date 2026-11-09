#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-premium-admin-dashboard.sh
if bash support/deploy-premium-admin-dashboard.sh --destroy >/dev/null 2>&1; then
  echo "Invalid deploy argument was accepted";exit 1
fi
grep -Fq "SOURCE_FILE_HASHES=6_OF_6_VERIFIED" support/deploy-premium-admin-dashboard.sh
grep -Fq 'SOURCE_STATE="ALREADY_UPDATED"' support/deploy-premium-admin-dashboard.sh
if grep -Eq 'migrate:(fresh|refresh|rollback)|db:wipe|artisan migrate --' support/deploy-premium-admin-dashboard.sh; then
  echo "Forbidden database migration operation";exit 1
fi
echo "PREMIUM_ADMIN_DASHBOARD_DEPLOYMENT_SYNTAX=PASS"
