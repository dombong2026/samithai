#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-starter30-content-importer.sh
grep -Fq 'SOURCE_FILES=8_OF_8_VERIFIED' support/deploy-starter30-content-importer.sh
grep -Fq 'STARTER30_SOURCE_PREFLIGHT=PASSED' support/deploy-starter30-content-importer.sh
grep -Fq 'STARTER30_SOURCE_INSTALL=SUCCESS' support/deploy-starter30-content-importer.sh
grep -Fq 'SOURCE_ROLLBACK=VERIFIED' support/deploy-starter30-content-importer.sh
grep -Fq 'DRAFT_DATASET=30_UNIQUE_PROMPTS_VERIFIED' support/deploy-starter30-content-importer.sh
grep -Fq 'DATABASE_MIGRATIONS=NONE' support/deploy-starter30-content-importer.sh
grep -Fq 'PROMPTS_CREATED=0' support/deploy-starter30-content-importer.sh
grep -Fq 'install -d -o root -g www -m 0755 "$dir"' support/deploy-starter30-content-importer.sh
grep -Fq 'test -d "$parent" && test ! -L "$parent"' support/deploy-starter30-content-importer.sh

if grep -Eq 'artisan migrate |migrate:(fresh|refresh|rollback)|db:wipe|DROP TABLE|\\-\\-apply --author-email' support/deploy-starter30-content-importer.sh; then
  echo 'BLOCKED=SOURCE_INSTALL_MAY_MUTATE_DATA'
  exit 1
fi
if bash support/deploy-starter30-content-importer.sh --destroy >/dev/null 2>&1; then
  echo 'BLOCKED=SOURCE_INSTALL_ACCEPTED_UNSUPPORTED_MODE'
  exit 1
fi
python3 - <<'PY'
from pathlib import Path
source=Path('support/deploy-starter30-content-importer.sh').read_text()
validate=source.index('PINNED_GITHUB_BLOBS_AND_JSON=VERIFIED')
mkdir=source.index('install -d -o root -g www -m 0755 "$dir"')
install=source.index('install -o root -g www -m 0644 "$WORK/$path" "$APP/$path"')
assert validate < mkdir < install, 'Must validate first, make dirs second, install files third'
assert 'STARTER_IMPORT_COMMAND=DISCOVERED' in source
assert 'PUBLICATION_ACTIONS=NONE' in source
print('STARTER30_SOURCE_INSTALL_SYNTAX=PASS')
PY


bash -n support/import-starter30-drafts.sh
grep -Fq 'DRAFT_IMPORT_PREFLIGHT=PASSED' support/import-starter30-drafts.sh
grep -Fq 'MYSQL_BACKUP=VERIFIED' support/import-starter30-drafts.sh
grep -Fq 'STARTER30_DRAFT_IMPORT=SUCCESS' support/import-starter30-drafts.sh
grep -Fq 'DRAFTS_ALREADY_MATCHING=30' support/import-starter30-drafts.sh
grep -Fq 'PUBLIC_PROMPTS_PUBLISHED_BY_IMPORT=0' support/import-starter30-drafts.sh
grep -Fq 'runuser -u www -- "$PHP" artisan "$COMMAND" --apply' support/import-starter30-drafts.sh
if grep -Eq 'migrate:(fresh|refresh|rollback)|db:wipe|artisan migrate |DROP TABLE' support/import-starter30-drafts.sh; then
  echo 'BLOCKED=UNEXPECTED_SCHEMA_MUTATION'; exit 1
fi
if bash support/import-starter30-drafts.sh --destroy >/dev/null 2>&1; then
  echo 'BLOCKED=UNSUPPORTED_IMPORT_MODE_ACCEPTED'; exit 1
fi
python3 - <<'PY_DB_GATES'
from pathlib import Path
s=Path('support/import-starter30-drafts.sh').read_text()
backup=s.index('echo "MYSQL_BACKUP=VERIFIED"')
import_start=s.index('runuser -u www -- "$PHP" artisan "$COMMAND" --apply')
verify=s.index('STARTER30_DRAFT_IMPORT=SUCCESS')
assert backup < import_start < verify, 'Backup must be verified before DB import'
assert 'runuser -u www -- "$PHP" artisan "$COMMAND" --check' in s
print('BACKUP_FIRST_DRAFT_ONLY_IMPORT_GATE=PASS')
PY_DB_GATES
