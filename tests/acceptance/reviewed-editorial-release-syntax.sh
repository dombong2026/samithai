#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/deploy-reviewed-content-publication.sh
bash -n support/release-reviewed-editorial-content.sh
grep -Fq 'SOURCE_FILES=1_OF_1_VERIFIED' support/deploy-reviewed-content-publication.sh
grep -Fq 'EDITORIAL_PUBLICATION_SOURCE_PREFLIGHT=PASSED' support/deploy-reviewed-content-publication.sh
grep -Fq 'EDITORIAL_PUBLICATION_SOURCE_INSTALL=SUCCESS' support/deploy-reviewed-content-publication.sh
grep -Fq 'CONTENT_RECORDS_MODIFIED=0' support/deploy-reviewed-content-publication.sh
grep -Fq 'EDITORIAL_PUBLICATION_COMMAND=DISCOVERED' support/deploy-reviewed-content-publication.sh
grep -Fq 'PUBLISH-SIX-REVIEWED-PAGES' support/release-reviewed-editorial-content.sh
grep -Fq 'PUBLISH-FIRST-REVIEWED-ARTICLE' support/release-reviewed-editorial-content.sh
grep -Fq 'PUBLISH-SECOND-REVIEWED-ARTICLE' support/release-reviewed-editorial-content.sh
grep -Fq 'EDITORIAL_RELEASE_PREFLIGHT=PASSED' support/release-reviewed-editorial-content.sh
grep -Fq 'MYSQL_BACKUP=VERIFIED' support/release-reviewed-editorial-content.sh
grep -Fq 'EDITORIAL_STAGE_PUBLICATION_AND_HTTP=VERIFIED' support/release-reviewed-editorial-content.sh
grep -Fq 'OTHER_SIX_BLOG_ARTICLES=UNCHANGED_DRAFTS' support/release-reviewed-editorial-content.sh
if grep -Eq 'artisan migrate |migrate:(fresh|refresh|rollback)|db:wipe|DROP TABLE' support/deploy-reviewed-content-publication.sh support/release-reviewed-editorial-content.sh; then
 echo 'BLOCKED=UNEXPECTED_SCHEMA_DESTRUCTION'; exit 1
fi
if bash support/deploy-reviewed-content-publication.sh --invalid >/dev/null 2>&1; then
 echo 'BLOCKED=UNSUPPORTED_INSTALL_MODE'; exit 1
fi
if bash support/release-reviewed-editorial-content.sh --invalid >/dev/null 2>&1; then
 echo 'BLOCKED=UNSUPPORTED_RELEASE_MODE'; exit 1
fi
python3 - <<'PY'
from pathlib import Path
deploy=Path('support/deploy-reviewed-content-publication.sh').read_text()
release=Path('support/release-reviewed-editorial-content.sh').read_text()
assert 'artisan migrate' not in deploy
assert deploy.index('PINNED_GITHUB_BLOBS_AND_JSON=VERIFIED') < deploy.index('install -o root -g www -m 0644 "$WORK/$path" "$APP/$path"')
assert release.index("echo 'MYSQL_BACKUP=VERIFIED'") < release.index('runuser -u www -- "$PHP" artisan "$COMMAND" "--$STAGE"')
assert release.index('runuser -u www -- "$PHP" artisan "$COMMAND" "--$STAGE"') < release.index('EDITORIAL_STAGE_PUBLICATION_AND_HTTP=VERIFIED')
print('EDITORIAL_STAGED_RELEASE_SCRIPT=PASS')
PY
