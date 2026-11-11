#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-educational-blog-drafts.sh
bash -n support/import-educational-blog-drafts.sh
grep -Fq 'SOURCE_FILES=5_OF_5_VERIFIED' support/deploy-educational-blog-drafts.sh
grep -Fq 'EDUCATIONAL_BLOG_DATASET=8_FULL_ARTICLES_VERIFIED' support/deploy-educational-blog-drafts.sh
grep -Fq 'EDUCATIONAL_BLOG_SOURCE_PREFLIGHT=PASSED' support/deploy-educational-blog-drafts.sh
grep -Fq 'EDUCATIONAL_BLOG_SOURCE_INSTALL=SUCCESS' support/deploy-educational-blog-drafts.sh
grep -Fq 'BLOG_POSTS_CREATED=0' support/deploy-educational-blog-drafts.sh
grep -Fq 'SOURCE_ROLLBACK=VERIFIED' support/deploy-educational-blog-drafts.sh
grep -Fq 'install -d -o root -g www -m 0755 "$dir"' support/deploy-educational-blog-drafts.sh
grep -Fq 'EDUCATIONAL_BLOG_IMPORT_PREFLIGHT=PASSED' support/import-educational-blog-drafts.sh
grep -Fq 'MYSQL_BACKUP=VERIFIED' support/import-educational-blog-drafts.sh
grep -Fq 'EDUCATIONAL_BLOG_DATABASE_IMPORT=SUCCESS' support/import-educational-blog-drafts.sh
grep -Fq 'PUBLIC_BLOG_ARTICLES_PUBLISHED_BY_IMPORT=0' support/import-educational-blog-drafts.sh
grep -Fq 'EXISTING_MATCHING_BLOG_DRAFTS=8' support/import-educational-blog-drafts.sh
grep -Fq 'runuser -u www -- "$PHP" artisan "$COMMAND" --apply' support/import-educational-blog-drafts.sh
if grep -Eq 'artisan migrate |migrate:(fresh|refresh|rollback)|db:wipe|DROP TABLE' \
    support/deploy-educational-blog-drafts.sh support/import-educational-blog-drafts.sh; then
  echo 'BLOCKED=UNEXPECTED_SCHEMA_MUTATION'
  exit 1
fi
if grep -Eq 'artisan.*samithai:import-educational-blogs.*--apply' support/deploy-educational-blog-drafts.sh; then
  echo 'BLOCKED=SOURCE_INSTALL_MUTATES_DB'
  exit 1
fi
if bash support/import-educational-blog-drafts.sh --unsupported >/dev/null 2>&1; then
  echo 'BLOCKED=UNSUPPORTED_MODE_ACCEPTED'
  exit 1
fi
python3 - <<'PY_GATES'
from pathlib import Path
s=Path('support/deploy-educational-blog-drafts.sh').read_text()
db=Path('support/import-educational-blog-drafts.sh').read_text()
assert s.index('PINNED_GITHUB_BLOBS_AND_JSON=VERIFIED') < s.index('install -d -o root -g www -m 0755 "$dir"') < s.index('install -o root -g www -m 0644 "$WORK/$path" "$APP/$path"')
assert db.index('echo "MYSQL_BACKUP=VERIFIED"') < db.index('runuser -u www -- "$PHP" artisan "$COMMAND" --apply') < db.index('EDUCATIONAL_BLOG_DATABASE_IMPORT=SUCCESS')
print('EIGHT_EDUCATIONAL_BLOG_RELEASE_GATE=PASS')
PY_GATES
