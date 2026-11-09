#!/usr/bin/env bash
# Samith AI: scoped creator review + marketplace sorting production upgrade.
# Operates on an operator-confirmed same-server encrypted backup. No offsite claim.
# --check checks prerequisites without modifying application/data.
# --apply briefly places application in maintenance, installs pinned files,
# applies ONE additive migration, then checks routes, inventory and HTTPS.
# If anything fails, code is restored; a created DB table is NOT auto-dropped.
set +x
set -Eeuo pipefail
umask 077

if [[ $# -ne 1 || ( "$1" != "--check" && "$1" != "--apply" ) ]]; then
    echo "Usage: bash support/deploy-feature-upgrade.sh --check|--apply" >&2
    exit 2
fi
MODE="$1"
SOURCE="b8bfc6983919fba3053c5a6623b75ef94c71d852"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
PHP="/www/server/php/85/bin/php"
VHOST="/www/server/panel/vhost/nginx/samithai.com.conf"
DOMAIN="samithai.com"
EVIDENCE="/root/SamithAI-Upgrade-Evidence"
SNAP="$EVIDENCE/local-snapshot.CPvak8Dz"
ENCRYPTED="$EVIDENCE/samithai-backup-20261109T040631Z.tar.gpg"
MIGRATION="2026_11_09_000100_create_prompt_reviews"
FILES="$(cat <<'FILEHASHES'
app/Http/Controllers/Dashboard/PromptController.php|17b3086b11bc1301307432ac859ecc756b01ccfa|84d66603f9f0bf5f40db9703810352724b82b920
app/Models/Prompt.php|b722be023d8c60085f38f6b3680266d9cbd9037a|b8fae803e0129de12bba8c0da31ca7766bfa4a3e
routes/web.php|868f9d371fc87a3f40addb28d1565b401205b047|dc4cec6e472e78f0094d5cef5d3e6da46b28e298
resources/views/dashboard/prompts.blade.php|9ebb291adc4aae3a395eea62b3f04da49f95a336|8b6b28dfcab1abcfa919832077f50bb885692dc4
public/assets/app.css|3e0795bc1ba85ffa4ac04a4a21a1029411e4257f|b31a7ecb961787d80f6c22fc06334fe94e4ad875
resources/css/app.css|54b247e2ae27b7ee170feeedfb5bcd7450ed622e|7f2574fc0fbc7decea7ae831c049ee39c8f0282a
app/Http/Controllers/MarketplaceController.php|1555aa59be8b837a9d98b302f147d617de610ceb|c8e566258ad113c42649a7794a2f1d01aaf470b0
resources/views/marketplace/index.blade.php|ffb78bfd25c7c4069570ee74115a6a5cc17cca16|098084ffe4f89acbe877cddc8e6705d1e6db9f2e
app/Support/RecoveryInventory.php|cf1668a8284ff20f97a53127fb6d66c53e227eb4|e36c8985e0ed2851f9436cd64e5796e6ea4a8759
app/Models/PromptReview.php|-|08b32ad45e6eb2cf6c70e3e80e5f43053f8fd30d
database/migrations/2026_11_09_000100_create_prompt_reviews.php|-|ac7c78b53f9894ebdd72907da217e6ff80b57e5d
resources/views/dashboard/prompt-reviews.blade.php|-|a9c389004718a6757adab191e5502298c34a5e51
FILEHASHES
)"

echo "========== SAMITH AI FEATURE UPGRADE PREFLIGHT =========="
test "$(id -u)" -eq 0
test -x "$PHP"
test -f "$APP/artisan" && test -f "$APP/vendor/autoload.php"
test -f "$APP/.env" && test -f "$APP/storage/app/installed.lock"
test -f "$VHOST"
test -d "$SNAP" && test ! -e "$SNAP/INCOMPLETE"
test -s "$ENCRYPTED" && test -s "$ENCRYPTED.sha256"
test -s "$SNAP/mysql.sql.gz" && test -s "$SNAP/application-state.tar.gz"
test -f "$SNAP/SHA256SUMS"
test -f "$EVIDENCE/pre-upgrade-inventory.json"
for cmd in curl git runuser flock sha256sum python3; do
    command -v "$cmd" >/dev/null
done
id www >/dev/null
grep -Fq "root $APP/public;" "$VHOST"

(
    cd "$SNAP"
    sha256sum --quiet -c SHA256SUMS
)
(
    cd "$EVIDENCE"
    sha256sum --quiet -c "$(basename "$ENCRYPTED").sha256"
)
python3 - "$EVIDENCE/pre-upgrade-inventory.json" <<'PY'
import json, sys
with open(sys.argv[1]) as f:
    data = json.load(f)
if (data.get('format') != 'samith-recovery-inventory/v2'
        or data.get('database_driver') != 'mysql'
        or len(data.get('tables', {})) != 13
        or data.get('media', {}).get('missing') != 0
        or data.get('media', {}).get('checksum_mismatch') != 0):
    raise SystemExit("BLOCKED: production recovery inventory is not the expected healthy v2 baseline")
PY
echo "LOCAL_ENCRYPTED_BACKUP_AND_V2_INVENTORY=VERIFIED"
echo "RISK=ENCRYPTED_BACKUP_IS_ON_SAME_SERVER;OFFSITE_RESTORE_NOT_TESTED"

while IFS='|' read -r path old new; do
    dest="$APP/$path"
    if [[ "$old" == "-" ]]; then
        if [[ -e "$dest" || -L "$dest" ]]; then
            echo "BLOCKED: unexpected existing upgrade file: $path"
            exit 1
        fi
    else
        if [[ ! -f "$dest" || -L "$dest" || "$(git hash-object "$dest")" != "$old" ]]; then
            echo "BLOCKED: modified or missing production file: $path"
            exit 1
        fi
    fi
done <<< "$FILES"
echo "EXISTING_PRODUCTION_SOURCE_HASHES=VERIFIED"

DB_PREFLIGHT="$(
    cd "$APP"
    runuser -u www -- "$PHP" -r '
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        if (config("database.default") !== "mysql"
            || !\Illuminate\Support\Facades\Schema::hasTable("migrations")
            || \Illuminate\Support\Facades\Schema::hasTable("prompt_reviews")
            || \Illuminate\Support\Facades\DB::table("migrations")->where("migration", "2026_11_09_000100_create_prompt_reviews")->exists()) {
            fwrite(STDERR, "BLOCKED: expected clean pre-upgrade MySQL state not found\n");
            exit(1);
        }
        echo "OK";
    '
)"
test "$DB_PREFLIGHT" = "OK"
echo "ADDITIVE_MIGRATION=NOT_APPLIED"

if [[ "$MODE" == "--check" ]]; then
    echo "UPGRADE_PRECHECK=PASSED"
    echo "APPLICATION_FILES=UNCHANGED"
    echo "DATABASE=UNCHANGED"
    echo "OFFSITE_RESTORE=NOT_VERIFIED"
    exit 0
fi

exec 9>/root/samithai-feature-upgrade.lock
flock -n 9 || { echo "BLOCKED: another feature upgrade is in progress"; exit 1; }
WORK="$(mktemp -d /root/samithai-feature-source.XXXXXXXX)"
BACKUP="$(mktemp -d /root/samithai-feature-code-backup.XXXXXXXX)"
CHANGED=0
MAINTENANCE=0

finish() {
    result=$?
    trap - EXIT INT TERM
    set +e
    if [[ "$result" -ne 0 && "$CHANGED" -eq 1 ]]; then
        echo "UPGRADE_FAILED=RESTORING_PREVIOUS_APPLICATION_CODE"
        while IFS='|' read -r path old new; do
            if [[ "$old" == "-" ]]; then
                rm -f "$APP/$path"
            else
                cp -p "$BACKUP/$path" "$APP/$path"
            fi
        done <<< "$FILES"
        (cd "$APP"; runuser -u www -- "$PHP" artisan route:clear; runuser -u www -- "$PHP" artisan view:clear) >/dev/null 2>&1
        echo "CODE_ROLLBACK=ATTEMPTED"
        echo "WARNING=ADDITIVE_DATABASE_TABLE_AND_MIGRATION_RECORD_MAY_REMAIN;NO_AUTOMATIC_DATABASE_RESTORE"
        echo "ROLLBACK_BACKUP_DIRECTORY=$BACKUP"
    fi
    if [[ "$MAINTENANCE" -eq 1 ]]; then
        (cd "$APP"; runuser -u www -- "$PHP" artisan up) >/dev/null 2>&1
        echo "MAINTENANCE_EXIT=ATTEMPTED"
    fi
    rm -rf "$WORK"
    exit "$result"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

echo "========== DOWNLOAD PINNED FEATURE SOURCE =========="
while IFS='|' read -r path old new; do
    mkdir -p "$WORK/$(dirname "$path")"
    curl -fsSL --proto '=https' --connect-timeout 10 --max-time 50 \
      "https://raw.githubusercontent.com/dombong2026/samithai/$SOURCE/$path" \
      -o "$WORK/$path"
    if [[ "$(git hash-object "$WORK/$path")" != "$new" ]]; then
        echo "BLOCKED: source integrity failed: $path"
        exit 1
    fi
    if [[ "$old" != "-" ]]; then
        mkdir -p "$BACKUP/$(dirname "$path")"
        cp -p "$APP/$path" "$BACKUP/$path"
    fi
done <<< "$FILES"
echo "PINNED_SOURCE_AND_CODE_BACKUP=VERIFIED"
echo "ROLLBACK_BACKUP_DIRECTORY=$BACKUP"

echo "========== BRIEF MAINTENANCE WINDOW =========="
(
    cd "$APP"
    runuser -u www -- "$PHP" artisan down --retry=60 --no-ansi
)
MAINTENANCE=1

echo "========== INSTALL SCOPED APPLICATION FILES =========="
CHANGED=1
while IFS='|' read -r path old new; do
    install -o root -g www -m 0644 "$WORK/$path" "$APP/$path"
    test "$(git hash-object "$APP/$path")" = "$new"
done <<< "$FILES"
echo "FEATURE_SOURCE=INSTALLED"

echo "========== ADD ONLY PROMPT REVIEW TABLE =========="
(
    cd "$APP"
    runuser -u www -- "$PHP" artisan migrate \
      --path=database/migrations/2026_11_09_000100_create_prompt_reviews.php \
      --force --no-interaction --no-ansi
)
DB_AFTER="$(
    cd "$APP"
    runuser -u www -- "$PHP" -r '
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        if (!\Illuminate\Support\Facades\Schema::hasTable("prompt_reviews")
            || !\Illuminate\Support\Facades\DB::table("migrations")->where("migration", "2026_11_09_000100_create_prompt_reviews")->exists()) {
            fwrite(STDERR, "BLOCKED: prompt review migration was not confirmed\n");
            exit(1);
        }
        echo "OK";
    '
)"
test "$DB_AFTER" = "OK"
echo "ADDITIVE_PROMPT_REVIEWS_MIGRATION=VERIFIED"

echo "========== REBUILD VIEWS, CHECK ROUTES =========="
(
    cd "$APP"
    runuser -u www -- "$PHP" artisan route:clear --no-ansi
    runuser -u www -- "$PHP" artisan view:clear --no-ansi
    runuser -u www -- "$PHP" artisan view:cache --no-ansi
    runuser -u www -- "$PHP" artisan route:list --except-vendor --no-ansi >/dev/null
)
echo "BLADE_AND_ROUTES=VERIFIED"

echo "========== CAPTURE POST-UPGRADE RECOVERY INVENTORY =========="
(
    cd "$APP"
    runuser -u www -- "$PHP" artisan samith:recovery-inventory --json --strict
) > "$BACKUP/post-upgrade-inventory-v3.json"
python3 - "$BACKUP/post-upgrade-inventory-v3.json" <<'PY'
import json,sys
with open(sys.argv[1]) as f:
    data=json.load(f)
if (data.get('format') != 'samith-recovery-inventory/v3'
        or 'prompt_reviews' not in data.get('tables',{})
        or data['tables']['prompt_reviews'].get('present') is not True
        or data.get('media',{}).get('missing') != 0
        or data.get('media',{}).get('checksum_mismatch') != 0):
    raise SystemExit("BLOCKED: new recovery inventory is unhealthy")
print("POST_UPGRADE_RECOVERY_INVENTORY_V3=VERIFIED")
PY

echo "========== EXIT MAINTENANCE =========="
(
    cd "$APP"
    runuser -u www -- "$PHP" artisan up --no-ansi
)
MAINTENANCE=0

echo "========== VERIFY LIVE ORIGIN =========="
for route in / /login /prompts '/prompts?sort=price_asc'; do
    http="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 \
      --resolve "$DOMAIN:443:127.0.0.1" \
      -o "$WORK/response.html" -w '%{http_code}' "https://$DOMAIN$route")"
    if [[ "$http" != "200" ]]; then
        echo "BLOCKED: origin HTTP status $http for $route"
        exit 1
    fi
done
grep -Fq 'Price: low to high' "$WORK/response.html"
css="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 \
  --resolve "$DOMAIN:443:127.0.0.1" \
  -o "$WORK/styles.css" -w '%{http_code}' "https://$DOMAIN/assets/app.css")"
test "$css" = "200"
grep -Fq '.prompt-review-note' "$WORK/styles.css"
echo "PUBLIC_HTTP_AND_CSS=VERIFIED"

CHANGED=0
echo "========== SAMITH AI FEATURE UPGRADE SUCCESS =========="
echo "PROMPT_REVIEW_FEEDBACK=INSTALLED"
echo "PRIVATE_REVIEW_TIMELINE=INSTALLED"
echo "MARKETPLACE_SORTING=INSTALLED"
echo "ADDITIVE_PROMPT_REVIEWS_TABLE=VERIFIED"
echo "RECOVERY_INVENTORY_V3=VERIFIED"
echo "LOCAL_ENCRYPTED_BACKUP=PRESERVED"
echo "OFFSITE_BACKUP_AND_RESTORE=NOT_VERIFIED"
echo "NGINX_AND_ENVIRONMENT=UNCHANGED"
echo "ROLLBACK_BACKUP_DIRECTORY=$BACKUP"
