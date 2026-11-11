#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-frontend-page-inventory.sh
grep -Fq 'SOURCE_FILES=5_OF_5_VERIFIED' support/deploy-frontend-page-inventory.sh
grep -Fq 'FRONTEND_PAGE_INVENTORY=SUCCESS' support/deploy-frontend-page-inventory.sh
grep -Fq 'FRONTEND_INVENTORY_ROUTES=VERIFIED' support/deploy-frontend-page-inventory.sh
grep -Fq 'PINNED_SOURCE_BLOBS_AND_PHP_SYNTAX=VERIFIED' support/deploy-frontend-page-inventory.sh
grep -Fq 'SOURCE_ROLLBACK=VERIFIED' support/deploy-frontend-page-inventory.sh
grep -Fq 'DATABASE_MIGRATIONS=NONE' support/deploy-frontend-page-inventory.sh
grep -Fq 'PROTECTED_AUTH_LEGAL_CHECKOUT=UNCHANGED' support/deploy-frontend-page-inventory.sh
if grep -Eq 'artisan migrate |migrate:(fresh|refresh|rollback)|db:wipe|DROP TABLE' support/deploy-frontend-page-inventory.sh; then
  echo 'Unexpected database operation'; exit 1
fi
if bash support/deploy-frontend-page-inventory.sh --destroy >/dev/null 2>&1; then
  echo 'Unsupported deployment mode accepted'; exit 1
fi
echo 'ALL_FRONTEND_PAGES_RELEASE_GATE=PASS'
