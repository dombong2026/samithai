#!/usr/bin/env bash
set -Eeuo pipefail
test -s support/samithai-editorial-cutover-plan.sh
test -s app/Console/Commands/ReviewSixEducationalDrafts.php
bash -n support/samithai-editorial-cutover-plan.sh
php -l app/Console/Commands/ReviewSixEducationalDrafts.php >/dev/null
grep -Fq 'PRODUCTION_MIGRATION=NOT_PERFORMED' support/samithai-editorial-cutover-plan.sh
grep -Fq 'human_review_and_publication' app/Console/Commands/ReviewSixEducationalDrafts.php
if grep -Eq 'migrate:(fresh|refresh)|db:wipe|DROP TABLE|TRUNCATE TABLE' \
   support/samithai-editorial-cutover-plan.sh app/Console/Commands/ReviewSixEducationalDrafts.php; then
  echo 'BLOCKED=DESTRUCTIVE_RELEASE_COMMAND'
  exit 1
fi
echo 'SAMITHAI_EDITORIAL_RELEASE_BLUEPRINT_STATIC=PASS'
