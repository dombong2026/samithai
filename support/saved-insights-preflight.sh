#!/usr/bin/env bash
# Read-only audit for the existing Samith AI production installation.
# This deliberately does NOT take a backup or deploy a migration.
set -Eeuo pipefail
umask 077
if [[ "$#" -ne 1 || "$1" != '--check' ]]; then
  echo 'Usage: bash support/saved-insights-preflight.sh --check' >&2
  exit 2
fi

APP='/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49'
PHP='/www/server/php/85/bin/php'
VHOST='/www/server/panel/vhost/nginx/samithai.com.conf'
FEATURE_SOURCE_SHA='8c7ea259d8d817bc9861929f8a1c24dc861f6f5f'
BEFORE="$(cat <<'EXPECTED'
app/Http/Controllers/AccountController.php|24898c960de9e4b9d7cc08a2cf746ee6fce052ec
app/Http/Controllers/Dashboard/OverviewController.php|c7c2200051de86c4d8cec91194383e5e80eef5d1
app/Http/Controllers/MarketplaceController.php|c8e566258ad113c42649a7794a2f1d01aaf470b0
app/Models/Prompt.php|b8fae803e0129de12bba8c0da31ca7766bfa4a3e
app/Models/User.php|714cf37e18eb885eb0e3c702030c216a2997a691
app/Support/RecoveryInventory.php|e36c8985e0ed2851f9436cd64e5796e6ea4a8759
public/assets/app.css|b31a7ecb961787d80f6c22fc06334fe94e4ad875
resources/css/app.css|7f2574fc0fbc7decea7ae831c049ee39c8f0282a
resources/views/account/index.blade.php|6d8bce725f817e1917ddf95379e2a8e5b7898507
resources/views/components/prompt-card.blade.php|4257fbdf4b2ae652948b304a12c7a050dc0302fe
resources/views/dashboard/overview.blade.php|6bccc319b9bf1be18bf781518f195bf8f0a3338c
resources/views/layouts/site.blade.php|0644c26372447dd42dde9b91f5f88c4d6ea4b268
resources/views/layouts/workspace.blade.php|660854dd4b48d653279409bba41e9f54aaabfe1a
resources/views/marketplace/index.blade.php|098084ffe4f89acbe877cddc8e6705d1e6db9f2e
resources/views/marketplace/show.blade.php|0361b457b755f580a213e419edfafde3c567d9d3
routes/web.php|dc4cec6e472e78f0094d5cef5d3e6da46b28e298
EXPECTED
)"
NEW="$(cat <<'NEWFILES'
app/Http/Controllers/CreatorInsightsController.php
app/Http/Controllers/SavedPromptController.php
database/migrations/2026_11_09_020000_create_prompt_saves_table.php
resources/views/account/creator-insights.blade.php
resources/views/account/saved.blade.php
NEWFILES
)"

echo '========== SAMITH AI SAVED + CREATOR INSIGHTS PRECHECK =========='
test "$(id -u)" -eq 0
test -x "$PHP" && test -f "$APP/artisan"
test -f "$APP/vendor/autoload.php" && test -f "$APP/.env"
test -f "$APP/storage/app/installed.lock"
test -f "$VHOST"
command -v git >/dev/null
command -v runuser >/dev/null
command -v python3 >/dev/null
id www >/dev/null
grep -Fq "root $APP/public;" "$VHOST" || {
  echo 'BLOCKED: Nginx is not serving the verified installation.'
  exit 1
}
while IFS='|' read -r path sha; do
  if [[ ! -f "$APP/$path" || -L "$APP/$path" || "$(git hash-object "$APP/$path")" != "$sha" ]]; then
    echo "BLOCKED: unexpected existing source: $path"
    exit 1
  fi
done <<< "$BEFORE"
echo 'CURRENT_SOURCE_HASHES=VERIFIED'

while IFS= read -r path; do
  if [[ -e "$APP/$path" || -L "$APP/$path" ]]; then
    echo "BLOCKED: feature file exists before installation: $path"
    exit 1
  fi
done <<< "$NEW"
echo 'NEW_FEATURE_FILES=ABSENT'

DB_STATE="$(
  cd "$APP"
  runuser -u www -- "$PHP" -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if (config("database.default") !== "mysql"
       || !\Illuminate\Support\Facades\Schema::hasTable("prompt_reviews")
       || \Illuminate\Support\Facades\Schema::hasTable("prompt_saves")
       || !\Illuminate\Support\Facades\DB::table("migrations")->where("migration","2026_11_09_000100_create_prompt_reviews")->exists()
       || \Illuminate\Support\Facades\DB::table("migrations")->where("migration","2026_11_09_020000_create_prompt_saves_table")->exists()) {
      fwrite(STDERR,"BLOCKED: unexpected existing migration state\n");
      exit(1);
    }
    echo "OK";
  '
)"
test "$DB_STATE" = OK
echo 'PREVIOUS_REVIEW_MIGRATION=INSTALLED'
echo 'SAVED_PROMPTS_MIGRATION=NOT_APPLIED'

(
  cd "$APP"
  runuser -u www -- "$PHP" artisan samith:recovery-inventory --json --strict
) | python3 -c '
import json,sys
data=json.load(sys.stdin)
assert data.get("format") == "samith-recovery-inventory/v3"
assert data.get("database_driver") == "mysql"
assert len(data.get("tables", {})) == 14
assert data.get("media", {}).get("missing") == 0
assert data.get("media", {}).get("checksum_mismatch") == 0
print("PRODUCTION_RECOVERY_INVENTORY_V3=HEALTHY")
'

echo '========== READ-ONLY PRECHECK RESULT =========='
echo "NEW_FEATURE_GITHUB_SHA=$FEATURE_SOURCE_SHA"
echo 'PRECHECK=PASSED'
echo 'CURRENT_V3_SNAPSHOT=REQUIRED_BEFORE_MIGRATION'
echo 'PREVIOUS_ENCRYPTED_V2_SNAPSHOT=NOT_CURRENT'
echo 'APPLICATION_FILES=UNCHANGED'
echo 'DATABASE=UNCHANGED'
echo 'PAYMENTS=UNCHANGED'
echo 'NGINX=UNCHANGED'
echo 'NEXT=Capture a new restricted v3 backup, then deploy the additive saved-prompts migration.'
