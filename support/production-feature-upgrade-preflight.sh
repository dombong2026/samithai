#!/usr/bin/env bash
# Read-only readiness check for an already installed samithai.com production upgrade.
# No downloads, database writes, migration, config changes, backups or deployments.
set -Eeuo pipefail
umask 077
if [[ $# -ne 1 || "$1" != "--check" ]]; then
  echo "Usage: bash support/production-feature-upgrade-preflight.sh --check" >&2
  exit 2
fi

DOMAIN="samithai.com"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
NGINX="/www/server/panel/vhost/nginx/$DOMAIN.conf"
PHP="/www/server/php/85/bin/php"
TARGET="925e7ca0f38c00050deac167f3817a0397ac5000"
BASELINES="$(cat <<'HASHES'
app/Http/Controllers/Dashboard/PromptController.php|17b3086b11bc1301307432ac859ecc756b01ccfa
app/Models/Prompt.php|b722be023d8c60085f38f6b3680266d9cbd9037a
routes/web.php|868f9d371fc87a3f40addb28d1565b401205b047
resources/views/dashboard/prompts.blade.php|9ebb291adc4aae3a395eea62b3f04da49f95a336
public/assets/app.css|3e0795bc1ba85ffa4ac04a4a21a1029411e4257f
resources/css/app.css|54b247e2ae27b7ee170feeedfb5bcd7450ed622e
app/Http/Controllers/MarketplaceController.php|1555aa59be8b837a9d98b302f147d617de610ceb
resources/views/marketplace/index.blade.php|ffb78bfd25c7c4069570ee74115a6a5cc17cca16
app/Support/RecoveryInventory.php|cf1668a8284ff20f97a53127fb6d66c53e227eb4
HASHES
)"
NEW_FILES="$(cat <<'PATHS'
app/Models/PromptReview.php
database/migrations/2026_11_09_000100_create_prompt_reviews.php
resources/views/dashboard/prompt-reviews.blade.php
PATHS
)"

echo "========== SAMITH AI PRODUCTION FEATURE-UPGRADE PREFLIGHT =========="
test "$(id -u)" = "0"
test -x "$PHP"
test -f "$APP/artisan"
test -f "$APP/vendor/autoload.php"
test -f "$APP/.env"
test -f "$APP/storage/app/installed.lock"
test -f "$NGINX"
command -v git >/dev/null
command -v runuser >/dev/null
id www >/dev/null
if ! grep -Fq "root $APP/public;" "$NGINX"; then
  echo "BLOCKED: Nginx is not serving the expected installed Laravel release."
  exit 1
fi

while IFS='|' read -r path hash; do
  file="$APP/$path"
  if [[ ! -f "$file" || -L "$file" || "$(git hash-object "$file")" != "$hash" ]]; then
    echo "BLOCKED: unexpected local changes: $path"
    exit 1
  fi
done <<< "$BASELINES"
echo "CURRENT_CODE_BASELINES=VERIFIED"

while IFS= read -r path; do
  if [[ -e "$APP/$path" || -L "$APP/$path" ]]; then
    echo "BLOCKED: upgrade file already exists: $path"
    exit 1
  fi
done <<< "$NEW_FILES"
echo "ADDITIVE_UPGRADE_FILES=NOT_YET_INSTALLED"

(
  cd "$APP"
  runuser -u www -- "$PHP" artisan migrate:status --no-ansi >/dev/null
)
echo "EXISTING_DATABASE_MIGRATION_STATUS=READABLE"

echo "========== UPGRADE DECISION =========="
echo "TARGET_GITHUB_SHA=$TARGET"
echo "PRODUCTION_REQUIRES_ADDITIVE_MIGRATION=YES"
echo "SOURCE_PRECHECK=PASSED"
echo "ACTUAL_OFFSITE_BACKUP_AND_RESTORE_EVIDENCE=NOT_CHECKED"
echo "UPGRADE_APPLIED=NO"
echo "DATABASE=UNCHANGED"
echo "NGINX=UNCHANGED"
echo "ENVIRONMENT=UNCHANGED"
echo "NEXT=Obtain independently verified offsite backup and migration/rollback acceptance before deploying."
