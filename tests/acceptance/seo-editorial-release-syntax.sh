#!/usr/bin/env bash
set -Eeuo pipefail
for path in \
 app/Models/SeoMetadata.php app/Models/ContentEditorialRevision.php \
 app/Support/SeoHead.php app/Support/EditorialGate.php \
 app/Http/Controllers/Dashboard/SeoManagerController.php \
 app/Http/Controllers/Dashboard/EditorialWorkflowController.php \
 app/Console/Commands/PublishScheduledEditorialContent.php \
 app/Console/Commands/SeedEducationalBlogSeo.php \
 app/Console/Commands/AuditEditorialRelease.php \
 database/migrations/2026_11_11_000200_create_seo_editorial_workflow.php \
 tests/Feature/SeoEditorialWorkflowTest.php \
 tests/Feature/SeoEditorialSafetyTest.php \
 tests/Feature/EducationalSeoSeedTest.php; do
  test -s "$path" || { echo "MISSING=$path"; exit 1; }
  php -l "$path" >/dev/null
done
grep -Fq "samithai:publish-scheduled-editorial" routes/console.php
grep -Fq "withoutOverlapping(10)" routes/console.php
grep -Fq "structured_data_enabled" database/migrations/2026_11_11_000200_create_seo_editorial_workflow.php
grep -Fq 'structured_data_enabled' app/Support/SeoHead.php
grep -Fq "whereHas('contentEntry'" app/Http/Controllers/Dashboard/MediaController.php
grep -Fq 'private function makeEntry(' tests/Feature/SeoEditorialSafetyTest.php
grep -Fq 'EditorialGate::approved' app/Console/Commands/PublishScheduledEditorialContent.php
grep -Fq 'samithai:editorial-release-audit' app/Console/Commands/AuditEditorialRelease.php
grep -Fq "SeoMetadata::where('og_media_id'" app/Http/Controllers/Dashboard/MediaController.php
grep -Fq "'og_media_id' => \$entry->seo?->og_media_id" app/Support/EditorialGate.php
if grep -Eq 'migrate:(fresh|refresh)|db:wipe|DROP TABLE|TRUNCATE TABLE' \
  app/Console/Commands/PublishScheduledEditorialContent.php \
  app/Console/Commands/SeedEducationalBlogSeo.php; then
  echo 'BLOCKED=DESTRUCTIVE_EDITORIAL_COMMAND'; exit 1
fi
echo 'SEO_EDITORIAL_RELEASE_STATIC_GATE=PASS'
