#!/usr/bin/env bash
# Samith AI: Saved Prompts, Most Saved sorting, Creator Insights, Admin Interest.
# An operator-approved scoped production upgrade using the verified current v3
# encrypted on-server backup. --check does not modify production.
# --apply installs pinned GitHub files and exactly one additive migration.
# Failure restores prior code and exits maintenance; never drops database data.
set +x
set -Eeuo pipefail
umask 077

if [[ $# -ne 1 || ( "$1" != "--check" && "$1" != "--apply" ) ]]; then
  echo "Usage: bash support/deploy-saved-insights.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
SOURCE="c53b985a5912c29356a36dea3413a0ba167e2d87"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
PHP="/www/server/php/85/bin/php"
DOMAIN="samithai.com"
VHOST="/www/server/panel/vhost/nginx/$DOMAIN.conf"
EVIDENCE="/root/SamithAI-Upgrade-Evidence"
ENCRYPTED="$EVIDENCE/samithai-current-v3-20261109T062845Z.tar.gpg"
MIGRATION="2026_11_09_020000_create_prompt_saves_table"
FILES="$(cat <<'CHECKSUMS'
app/Http/Controllers/AccountController.php|24898c960de9e4b9d7cc08a2cf746ee6fce052ec|ec953ee6bbe4628eb4251d477829562e763099f1
app/Http/Controllers/CreatorInsightsController.php|-|daa32d5abbc8f85346ca89fccfc78a7e0a5633ba
app/Http/Controllers/Dashboard/OverviewController.php|c7c2200051de86c4d8cec91194383e5e80eef5d1|916a25f305eafd0b1cd41c13ed1dd9f68463f449
app/Http/Controllers/MarketplaceController.php|c8e566258ad113c42649a7794a2f1d01aaf470b0|d14b0a707f6fff6500443f475ee456fd524d9936
app/Http/Controllers/SavedPromptController.php|-|6fa5f6ce0ade6f6557b528181a15e3f47c4a79a5
app/Models/Prompt.php|b8fae803e0129de12bba8c0da31ca7766bfa4a3e|16760f0a226df9f19e16cb08dbc8747afc4ae9d0
app/Models/User.php|714cf37e18eb885eb0e3c702030c216a2997a691|2b396f9d8d6a6436fd85968bec9239225fec8272
app/Support/RecoveryInventory.php|e36c8985e0ed2851f9436cd64e5796e6ea4a8759|04227f868d0c33d691a339d124b4b685da7d8031
database/migrations/2026_11_09_020000_create_prompt_saves_table.php|-|8967419232a5418f1b2266cf349a661d4d40927c
public/assets/app.css|b31a7ecb961787d80f6c22fc06334fe94e4ad875|287206fe55fb03d0a8a1857edd4c5a3d1f8a9b41
resources/css/app.css|7f2574fc0fbc7decea7ae831c049ee39c8f0282a|d2f8952b80a94157c8ebffe2f8626a1ca1d970a5
resources/views/account/creator-insights.blade.php|-|58f94e3fa5a654d51a74dc2e10d4441230490001
resources/views/account/index.blade.php|6d8bce725f817e1917ddf95379e2a8e5b7898507|fbf87664274e4835e4bf9967268352763566701a
resources/views/account/saved.blade.php|-|9212b197aef877658dd4e3ad71494a39bbbcb6a2
resources/views/components/prompt-card.blade.php|4257fbdf4b2ae652948b304a12c7a050dc0302fe|4ba0b5be8138469055472df2b31c023a6cd7d0a6
resources/views/dashboard/overview.blade.php|6bccc319b9bf1be18bf781518f195bf8f0a3338c|58349229fd1cd85b71e081e15363e3483b547285
resources/views/layouts/site.blade.php|0644c26372447dd42dde9b91f5f88c4d6ea4b268|2c41f82223c99e37364284b777e5687a21183260
resources/views/layouts/workspace.blade.php|660854dd4b48d653279409bba41e9f54aaabfe1a|8617ab0195283f671cb88b2950d4718d0b09195e
resources/views/marketplace/index.blade.php|098084ffe4f89acbe877cddc8e6705d1e6db9f2e|5c9ca24ac302df71f1494eadf581cc90c7e13ca3
resources/views/marketplace/show.blade.php|0361b457b755f580a213e419edfafde3c567d9d3|b07809abe919ff49904cab1e6f99bed75f49780c
routes/web.php|dc4cec6e472e78f0094d5cef5d3e6da46b28e298|f3250c3dff719de816cc437a5d9bc0beeb2373fe
CHECKSUMS
)"

echo "========== SAVED PROMPTS + CREATOR INSIGHTS PREFLIGHT =========="
test "$(id -u)" = "0"
test -x "$PHP"
test -f "$APP/artisan" && test -f "$APP/vendor/autoload.php"
test -f "$APP/.env" && test -f "$APP/storage/app/installed.lock"
test -f "$VHOST" && test -d "$EVIDENCE"
test ! -L "$EVIDENCE"
test -s "$ENCRYPTED" && test -s "$ENCRYPTED.sha256"
test ! -L "$ENCRYPTED" && test ! -L "$ENCRYPTED.sha256"
test "$(stat -c '%u' "$ENCRYPTED")" = "0"
test "$(stat -c '%a' "$ENCRYPTED")" = "600"
for executable in curl git runuser flock sha256sum python3; do command -v "$executable" >/dev/null; done
id www >/dev/null
grep -Fq "root $APP/public;" "$VHOST" || { echo "BLOCKED: Nginx root mismatch"; exit 1; }
if [[ -e "$APP/storage/framework/down" || -e "$APP/storage/framework/maintenance.php" ]]; then
  echo "BLOCKED: existing application maintenance mode"; exit 1
fi

(
  cd "$EVIDENCE"
  sha256sum --quiet -c "$(basename "$ENCRYPTED").sha256"
)
echo "CURRENT_V3_AES256_BACKUP_SHA256=VERIFIED"
echo "OFFSITE_RESTORE=NOT_VERIFIED"

while IFS='|' read -r path old new; do
  if [[ "$old" == "-" ]]; then
    if [[ -e "$APP/$path" || -L "$APP/$path" ]]; then
      echo "BLOCKED: new feature file already installed: $path"; exit 1
    fi
  elif [[ ! -f "$APP/$path" || -L "$APP/$path" || "$(git hash-object "$APP/$path")" != "$old" ]]; then
    echo "BLOCKED: changed or missing production file: $path"; exit 1
  fi
done <<< "$FILES"
echo "CURRENT_PRODUCTION_CODE=VERIFIED"

DB_STATE="$(
  cd "$APP"
  runuser -u www -- "$PHP" -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if (config("database.default") !== "mysql"
        || !\Illuminate\Support\Facades\Schema::hasTable("prompt_reviews")
        || \Illuminate\Support\Facades\Schema::hasTable("prompt_saves")
        || !\Illuminate\Support\Facades\DB::table("migrations")
            ->where("migration","2026_11_09_000100_create_prompt_reviews")->exists()
        || \Illuminate\Support\Facades\DB::table("migrations")
            ->where("migration","2026_11_09_020000_create_prompt_saves_table")->exists()) {
      fwrite(STDERR, "BLOCKED: unexpected source database state\n");
      exit(1);
    }
    echo "OK";
  '
)"
test "$DB_STATE" = "OK"
echo "PROMPT_REVIEWS_SCHEMA=VERIFIED"
echo "PROMPT_SAVES_SCHEMA=NOT_INSTALLED"

(
  cd "$APP"
  runuser -u www -- "$PHP" artisan samith:recovery-inventory --json --strict
) | python3 -c '
import json,sys
d=json.load(sys.stdin)
if (d.get("format")!="samith-recovery-inventory/v3"
    or len(d.get("tables",{}))!=14
    or not d.get("tables",{}).get("prompt_reviews",{}).get("present")
    or d.get("media",{}).get("missing")!=0
    or d.get("media",{}).get("checksum_mismatch")!=0):
    raise SystemExit("BLOCKED: current recovery inventory is unhealthy")
print("CURRENT_RECOVERY_INVENTORY_V3=HEALTHY")
'

if [[ "$MODE" == "--check" ]]; then
  echo "READ_ONLY_UPGRADE_CHECK=PASSED"
  echo "PRODUCTION_FILES_AND_DATABASE=UNCHANGED"
  echo "ENCRYPTED_BACKUP=PRESERVED"
  exit 0
fi

exec 9>/root/samithai-feature-upgrade.lock
flock -n 9 || { echo "BLOCKED: another feature upgrade is running"; exit 1; }
WORK="$(mktemp -d /root/samithai-saved-source.XXXXXXXX)"
BACKUP="$(mktemp -d /root/samithai-saved-code-backup.XXXXXXXX)"
CHANGED=0
MAINTENANCE=0

finish() {
  result=$?
  trap - EXIT INT TERM
  set +e
  if [[ "$result" -ne 0 && "$CHANGED" -eq 1 ]]; then
    echo "UPGRADE_FAILED=ATTEMPT_CODE_ROLLBACK"
    while IFS='|' read -r path old new; do
      if [[ "$old" == "-" ]]; then
        rm -f -- "$APP/$path"
      else
        cp -p -- "$BACKUP/$path" "$APP/$path"
      fi
    done <<< "$FILES"
    ( cd "$APP"; runuser -u www -- "$PHP" artisan route:clear --no-ansi; runuser -u www -- "$PHP" artisan view:clear --no-ansi; ) >/dev/null 2>&1
    echo "PREVIOUS_CODE=RESTORE_ATTEMPTED"
    echo "DATABASE_MIGRATION_MAY_REMAIN=YES"
    echo "DATABASE_WAS_NOT_AUTOMATICALLY_ROLLED_BACK"
    echo "ROLLBACK_BACKUP_DIRECTORY=$BACKUP"
  fi
  if [[ "$MAINTENANCE" -eq 1 ]]; then
    (cd "$APP"; runuser -u www -- "$PHP" artisan up --no-ansi) >/dev/null 2>&1
    echo "MAINTENANCE_EXIT=ATTEMPTED"
  fi
  rm -rf -- "$WORK"
  exit "$result"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

echo "========== FETCH AND VERIFY PINNED SOURCE =========="
while IFS='|' read -r path old new; do
  mkdir -p "$WORK/$(dirname "$path")"
  curl -fsSL --proto '=https' --connect-timeout 10 --max-time 50 \
    "https://raw.githubusercontent.com/dombong2026/samithai/$SOURCE/$path" \
    -o "$WORK/$path"
  test "$(git hash-object "$WORK/$path")" = "$new" || {
    echo "BLOCKED: checksum mismatch: $path"; exit 1
  }
  if [[ "$old" != "-" ]]; then
    mkdir -p "$BACKUP/$(dirname "$path")"
    cp -p "$APP/$path" "$BACKUP/$path"
  fi
done <<< "$FILES"
echo "SOURCE_HASHES_AND_CODE_BACKUP=VERIFIED"
echo "ROLLBACK_BACKUP_DIRECTORY=$BACKUP"

echo "========== ENTER BRIEF MAINTENANCE =========="
MAINTENANCE=1
( cd "$APP"; runuser -u www -- "$PHP" artisan down --retry=60 --no-ansi )

echo "========== APPLY REVIEWED CODE FILES =========="
CHANGED=1
while IFS='|' read -r path old new; do
  install -o root -g www -m 0644 "$WORK/$path" "$APP/$path"
  test "$(git hash-object "$APP/$path")" = "$new"
done <<< "$FILES"
echo "SAVED_PROMPTS_AND_CREATOR_INSIGHTS_SOURCE=INSTALLED"

echo "========== APPLY ONLY ADDITIVE SAVED PROMPTS MIGRATION =========="
( cd "$APP"; runuser -u www -- "$PHP" artisan migrate \
  --path=database/migrations/2026_11_09_020000_create_prompt_saves_table.php \
  --force --no-interaction --no-ansi )
AFTER="$(
  cd "$APP"
  runuser -u www -- "$PHP" -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if (!\Illuminate\Support\Facades\Schema::hasTable("prompt_saves")
        || !\Illuminate\Support\Facades\Schema::hasColumns("prompt_saves",
              ["id","user_id","prompt_id","created_at","updated_at"])
        || !\Illuminate\Support\Facades\DB::table("migrations")
            ->where("migration","2026_11_09_020000_create_prompt_saves_table")->exists()) {
      fwrite(STDERR,"BLOCKED: additive saved-prompt schema not verified\n");
      exit(1);
    }
    echo "OK";
  '
)"
test "$AFTER" = "OK"
echo "ADDITIVE_PROMPT_SAVES_MIGRATION=VERIFIED"

echo "========== COMPILE BLADE VIEWS AND ROUTES =========="
( cd "$APP"
  runuser -u www -- "$PHP" artisan route:clear --no-ansi
  runuser -u www -- "$PHP" artisan view:clear --no-ansi
  runuser -u www -- "$PHP" artisan view:cache --no-ansi
  runuser -u www -- "$PHP" artisan route:list --except-vendor --no-ansi >/dev/null
)
echo "BLADE_AND_ROUTES=VERIFIED"

echo "========== POST-UPGRADE RECOVERY INVENTORY V4 =========="
( cd "$APP"; runuser -u www -- "$PHP" artisan samith:recovery-inventory --json --strict ) \
  > "$BACKUP/post-upgrade-v4-inventory.json"
python3 - "$BACKUP/post-upgrade-v4-inventory.json" <<'PY'
import json,sys
with open(sys.argv[1]) as f: d=json.load(f)
if (d.get("format")!="samith-recovery-inventory/v4"
    or len(d.get("tables",{}))!=15
    or not d.get("tables",{}).get("prompt_saves",{}).get("present")
    or d.get("media",{}).get("missing")!=0
    or d.get("media",{}).get("checksum_mismatch")!=0):
    raise SystemExit("BLOCKED: post-upgrade inventory v4 is unhealthy")
print("POST_UPGRADE_V4_INVENTORY=HEALTHY")
PY
chmod 600 "$BACKUP/post-upgrade-v4-inventory.json"

echo "========== EXIT MAINTENANCE =========="
( cd "$APP"; runuser -u www -- "$PHP" artisan up --no-ansi )
MAINTENANCE=0

echo "========== VERIFY PUBLIC LIVE ORIGIN =========="
for page in "/" "/login" "/prompts" "/prompts?sort=most_saved"; do
  status="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 \
    --resolve "$DOMAIN:443:127.0.0.1" \
    -o "$WORK/response.html" -w '%{http_code}' "https://$DOMAIN$page")"
  test "$status" = "200" || { echo "BLOCKED: HTTP $status for $page"; exit 1; }
done
grep -Fq 'Most saved' "$WORK/response.html"
echo "PUBLIC_CATALOG_AND_TRENDING_SORT=VERIFIED"

code="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 \
  --resolve "$DOMAIN:443:127.0.0.1" \
  -o "$WORK/assets.css" -w '%{http_code}' "https://$DOMAIN/assets/app.css")"
test "$code" = "200"
grep -Fq '.save-toggle' "$WORK/assets.css"
echo "SAVED_CARD_CSS=VERIFIED"

for page in "/account/saved" "/account/creator/insights"; do
  status="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 \
    --resolve "$DOMAIN:443:127.0.0.1" \
    -D "$WORK/headers.txt" -o "$WORK/private.html" \
    -w '%{http_code}' "https://$DOMAIN$page")"
  test "$status" = "302" || { echo "BLOCKED: unauthenticated $page returned HTTP $status"; exit 1; }
  grep -Eiq '^location: .*/login' "$WORK/headers.txt" || {
    echo "BLOCKED: unauthenticated workspace did not redirect to login"; exit 1
  }
done
echo "PRIVATE_WORKSPACE_GUEST_GUARDS=VERIFIED"

CHANGED=0
echo "========== SAMITH AI SAVED + INSIGHTS UPGRADE PASSED =========="
echo "SAVED_PROMPTS=INSTALLED"
echo "MOST_SAVED_SORT=INSTALLED"
echo "CREATOR_INSIGHTS=INSTALLED"
echo "ADMIN_MARKETPLACE_INTEREST=INSTALLED"
echo "PROMPT_SAVES_DATABASE=VERIFIED"
echo "RECOVERY_INVENTORY_V4=VERIFIED"
echo "PRIOR_PROMPT_REVIEWS_AND_LICENSES=PRESERVED"
echo "LOCAL_AES256_BACKUP=PRESERVED"
echo "PAYMENT_NGINX_ENV=UNCHANGED"
echo "OFFSITE_RESTORE=NOT_VERIFIED"
echo "ROLLBACK_BACKUP_DIRECTORY=$BACKUP"
