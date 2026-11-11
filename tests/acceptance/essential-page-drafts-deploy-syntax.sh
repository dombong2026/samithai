#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-essential-page-drafts.sh
bash -n support/import-essential-page-drafts.sh
grep -Fq 'SOURCE_FILES=3_OF_3_VERIFIED' support/deploy-essential-page-drafts.sh
grep -Fq 'ESSENTIAL_PAGE_DATASET=6_COMPLETE_DRAFTS_VERIFIED' support/deploy-essential-page-drafts.sh
grep -Fq 'ESSENTIAL_PAGE_SOURCE_PREFLIGHT=PASSED' support/deploy-essential-page-drafts.sh
grep -Fq 'ESSENTIAL_PAGE_SOURCE_INSTALL=SUCCESS' support/deploy-essential-page-drafts.sh
grep -Fq 'PAGE_RECORDS_CREATED=0' support/deploy-essential-page-drafts.sh
grep -Fq 'SOURCE_ROLLBACK=VERIFIED' support/deploy-essential-page-drafts.sh
grep -Fq 'install -d -o root -g www -m 0755 "$dir"' support/deploy-essential-page-drafts.sh
grep -Fq 'ESSENTIAL_PAGE_IMPORT_PREFLIGHT=PASSED' support/import-essential-page-drafts.sh
grep -Fq 'MYSQL_BACKUP=VERIFIED' support/import-essential-page-drafts.sh
grep -Fq 'ESSENTIAL_PAGE_DATABASE_IMPORT=SUCCESS' support/import-essential-page-drafts.sh
grep -Fq 'PUBLIC_PAGES_PUBLISHED_BY_IMPORT=0' support/import-essential-page-drafts.sh
grep -Fq 'EXISTING_MATCHING_DRAFTS=6' support/import-essential-page-drafts.sh
grep -Fq 'runuser -u www -- "$PHP" artisan "$COMMAND" --apply' support/import-essential-page-drafts.sh
if grep -Eq 'artisan migrate |migrate:(fresh|refresh|rollback)|db:wipe|DROP TABLE' \
    support/deploy-essential-page-drafts.sh support/import-essential-page-drafts.sh; then
  echo 'BLOCKED=UNEXPECTED_DB_SCHEMA_OPERATION'
  exit 1
fi
if grep -Eq 'artisan.*samithai:import-essential-pages.*--apply' support/deploy-essential-page-drafts.sh; then
  echo 'BLOCKED=SOURCE_INSTALL_WRITES_PAGES'
  exit 1
fi
if bash support/import-essential-page-drafts.sh --unsafe >/dev/null 2>&1; then
  echo 'BLOCKED=UNSUPPORTED_MODE_ACCEPTED'
  exit 1
fi
python3 - <<'PY_CHECKS'
from pathlib import Path
install=Path('support/deploy-essential-page-drafts.sh').read_text()
db=Path('support/import-essential-page-drafts.sh').read_text()
assert install.index('PINNED_GITHUB_BLOBS_AND_JSON=VERIFIED') < install.index('install -d -o root -g www -m 0755 "$dir"') < install.index('install -o root -g www -m 0644 "$WORK/$path" "$APP/$path"')
assert db.index('echo "MYSQL_BACKUP=VERIFIED"') < db.index('runuser -u www -- "$PHP" artisan "$COMMAND" --apply')
assert db.index('runuser -u www -- "$PHP" artisan "$COMMAND" --apply') < db.index('ESSENTIAL_PAGE_DATABASE_IMPORT=SUCCESS')
print('SIX_ESSENTIAL_PAGES_RELEASE_GATE=PASS')
PY_CHECKS
