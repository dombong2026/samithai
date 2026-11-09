#!/usr/bin/env bash
# Reconcile an already-installed, exact-hash Samith AI Saved Prompts release.
# --check reads only. --finish applies ONLY the additive missing migration, if
# needed; no source file replacement, database restore, or destructive migration.
set +x
set -Eeuo pipefail
umask 077

if [[ $# -ne 1 || ( "$1" != "--check" && "$1" != "--finish" ) ]]; then
  echo "Usage: reconcile-saved-insights.sh --check|--finish" >&2
  exit 2
fi

MODE="$1"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
PHP="/www/server/php/85/bin/php"
DOMAIN="samithai.com"
VHOST="/www/server/panel/vhost/nginx/$DOMAIN.conf"
EVIDENCE="/root/SamithAI-Upgrade-Evidence"
ENCRYPTED="$EVIDENCE/samithai-current-v3-20261109T062845Z.tar.gpg"
PRIOR_SCRIPT="/root/samithai-saved-insights-upgrade.sh"
PRIOR_SCRIPT_BLOB="4c167bffca1a61576a2c1cd99caca63cae006104"
MIGRATION="2026_11_09_020000_create_prompt_saves_table"

echo "========== SAFE SAVED INSIGHTS RECONCILIATION =========="
test "$(id -u)" -eq 0
for cmd in git python3 runuser flock curl sha256sum; do
  command -v "$cmd" >/dev/null || { echo "BLOCKED: missing $cmd"; exit 1; }
done
test -x "$PHP"
test -f "$APP/artisan" && test -f "$APP/vendor/autoload.php"
test -f "$APP/.env" && test -f "$APP/storage/app/installed.lock"
test -f "$VHOST" && test -s "$PRIOR_SCRIPT"
test -s "$ENCRYPTED" && test -s "$ENCRYPTED.sha256"
test ! -L "$ENCRYPTED" && test ! -L "$ENCRYPTED.sha256"
test -d "$EVIDENCE" && test ! -L "$EVIDENCE"
id www >/dev/null
grep -Fq "root $APP/public;" "$VHOST" || {
  echo "BLOCKED: production Nginx root does not match"; exit 1;
}
if [[ -e "$APP/storage/framework/down" || -e "$APP/storage/framework/maintenance.php" ]]; then
  echo "BLOCKED: the production site is already in maintenance mode"; exit 1;
fi

(
  cd "$EVIDENCE"
  sha256sum --quiet -c "$(basename "$ENCRYPTED").sha256"
)
echo "CURRENT_ENCRYPTED_V3_BACKUP_SHA256=VERIFIED"
echo "OFFSITE_RESTORE=NOT_VERIFIED"

test "$(git hash-object "$PRIOR_SCRIPT")" = "$PRIOR_SCRIPT_BLOB" || {
  echo "BLOCKED: the original upgrade script was changed"; exit 1;
}
python3 - "$APP" "$PRIOR_SCRIPT" <<'PY'
from pathlib import Path
import subprocess
import sys

app = Path(sys.argv[1])
source = Path(sys.argv[2]).read_text()
start = "FILES=\"$(cat <<'CHECKSUMS'\n"
end = "\nCHECKSUMS\n)"
if source.count(start) != 1 or source.count(end) != 1:
    raise SystemExit("BLOCKED: unexpected original release manifest")
block = source.split(start, 1)[1].split(end, 1)[0]
lines = block.splitlines()
if len(lines) != 21:
    raise SystemExit("BLOCKED: expected 21 reviewed application files")
for entry in lines:
    path, old, target = entry.split("|")
    item = app / path
    if not item.is_file() or item.is_symlink():
        raise SystemExit("BLOCKED: release file missing or a symlink: "+path)
    actual = subprocess.check_output(["git", "hash-object", str(item)], text=True).strip()
    if actual != target:
        raise SystemExit("BLOCKED: file no longer matches new release: "+path)
print("INSTALLED_NEW_RELEASE_FILES=21_OF_21_MATCHED")
print("SOURCE_FILES=UNCHANGED")
PY

check_schema() {
  (
    cd "$APP"
    runuser -u www -- "$PHP" -r '
      require "vendor/autoload.php";
      $app = require "bootstrap/app.php";
      $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
      $schema = \Illuminate\Support\Facades\Schema::class;
      $db = \Illuminate\Support\Facades\DB::class;
      if ($db::getDriverName() !== "mysql"
          || !$schema::hasTable("prompt_reviews")
          || !$schema::hasTable("migrations")
          || !$db::table("migrations")->where("migration", "2026_11_09_000100_create_prompt_reviews")->exists()) {
          fwrite(STDERR, "BLOCKED: unexpected production database baseline\n");
          exit(1);
      }
      $table = $schema::hasTable("prompt_saves");
      $record = $db::table("migrations")->where("migration", "2026_11_09_020000_create_prompt_saves_table")->exists();
      if ($table !== $record) {
          fwrite(STDERR, "BLOCKED: table and migration record disagree; inspect manually\n");
          exit(1);
      }
      if ($table && !$schema::hasColumns("prompt_saves", ["id", "user_id", "prompt_id", "created_at", "updated_at"])) {
          fwrite(STDERR, "BLOCKED: existing saved-prompt schema is incomplete\n");
          exit(1);
      }
      echo $table ? "INSTALLED" : "PENDING";
    '
  )
}
STATE="$(check_schema)"
if [[ "$STATE" != "INSTALLED" && "$STATE" != "PENDING" ]]; then
  echo "BLOCKED: unexpected database status"; exit 1
fi
echo "PROMPT_SAVES_MIGRATION_STATUS=$STATE"

if [[ "$MODE" == "--check" ]]; then
  echo "RECONCILIATION_PREFLIGHT=PASSED"
  echo "DATABASE=UNCHANGED"
  if [[ "$STATE" == "PENDING" ]]; then
    echo "NEXT=Run --finish to apply only the missing additive migration"
  else
    echo "NEXT=Run --finish for final route, inventory and HTTPS verification"
  fi
  exit 0
fi

exec 9>/root/samithai-feature-upgrade.lock
flock -n 9 || { echo "BLOCKED: another feature deployment is running"; exit 1; }
test "$(git hash-object "$PRIOR_SCRIPT")" = "$PRIOR_SCRIPT_BLOB"
STATE="$(check_schema)"
MAINTENANCE=0
TEMP="$(mktemp -d /root/samithai-saved-reconcile.XXXXXXXX)"

cleanup() {
  result=$?
  trap - EXIT INT TERM
  set +e
  if [[ "$MAINTENANCE" -eq 1 ]]; then
    (cd "$APP"; runuser -u www -- "$PHP" artisan up --no-ansi) >/dev/null 2>&1
    echo "MAINTENANCE_EXIT=ATTEMPTED"
  fi
  rm -rf -- "$TEMP"
  if [[ "$result" -ne 0 ]]; then
    echo "RECONCILIATION_FAILED=REVIEW_LOGS"
    echo "DATABASE_AUTOMATIC_ROLLBACK=NOT_PERFORMED"
    echo "SOURCE_CODE=UNCHANGED"
  fi
  exit "$result"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ "$STATE" == "PENDING" ]]; then
  echo "========== ENTER SHORT MAINTENANCE WINDOW =========="
  MAINTENANCE=1
  (cd "$APP"; runuser -u www -- "$PHP" artisan down --retry=60 --no-ansi)

  echo "========== APPLY ONLY ADDITIVE SAVED-PROMPTS TABLE =========="
  (cd "$APP"; runuser -u www -- "$PHP" artisan migrate \
     --path=database/migrations/2026_11_09_020000_create_prompt_saves_table.php \
     --force --no-interaction --no-ansi)
  test "$(check_schema)" = "INSTALLED"
  echo "PROMPT_SAVES_MIGRATION=APPLIED_AND_VERIFIED"

  echo "========== REBUILD VIEW AND ROUTE CACHES =========="
  (
    cd "$APP"
    runuser -u www -- "$PHP" artisan route:clear --no-ansi
    runuser -u www -- "$PHP" artisan view:clear --no-ansi
    runuser -u www -- "$PHP" artisan view:cache --no-ansi
  )

  (cd "$APP"; runuser -u www -- "$PHP" artisan up --no-ansi)
  MAINTENANCE=0
else
  echo "PROMPT_SAVES_MIGRATION=ALREADY_INSTALLED"
  echo "MIGRATION_AND_APPLICATION_CODE=NOT_CHANGED"
fi

echo "========== VALIDATE ACCOUNT AND SAVE ROUTES =========="
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan route:list --except-vendor --no-ansi
) > "$TEMP/routes.txt"
for route_name in account.saved account.creator.insights prompts.save prompts.unsave; do
  grep -Fq "$route_name" "$TEMP/routes.txt" || {
    echo "BLOCKED: missing route $route_name"; exit 1;
  }
done
echo "NEW_AUTHENTICATED_ROUTES=VERIFIED"

echo "========== CHECK POST-UPGRADE V4 INVENTORY =========="
POST="$(mktemp "$EVIDENCE/post-saved-insights-v4.XXXXXXXX.json")"
chmod 600 "$POST"
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan samith:recovery-inventory --json --strict
) > "$POST"
python3 - "$POST" <<'PY'
import json,sys
with open(sys.argv[1]) as f: d=json.load(f)
if (d.get("format") != "samith-recovery-inventory/v4"
    or len(d.get("tables", {})) != 15
    or not d.get("tables", {}).get("prompt_saves", {}).get("present")
    or not d.get("tables", {}).get("prompt_reviews", {}).get("present")
    or d.get("media", {}).get("missing") != 0
    or d.get("media", {}).get("checksum_mismatch") != 0):
    raise SystemExit("BLOCKED: recovery inventory v4 is incomplete")
print("POST_UPGRADE_RECOVERY_INVENTORY_V4=HEALTHY")
PY

echo "========== VERIFY LIVE MARKETPLACE ORIGIN =========="
for page in "/" "/login" "/prompts" "/prompts?sort=most_saved"; do
  status="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 \
    --resolve "$DOMAIN:443:127.0.0.1" \
    -o "$TEMP/page.html" -w '%{http_code}' "https://$DOMAIN$page")"
  if [[ "$status" != "200" ]]; then
    echo "BLOCKED: $page returned HTTP $status"; exit 1;
  fi
done
grep -Fq 'Most saved' "$TEMP/page.html" || {
  echo "BLOCKED: most-saved sort missing"; exit 1;
}
status="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 \
  --resolve "$DOMAIN:443:127.0.0.1" \
  -o "$TEMP/style.css" -w '%{http_code}' "https://$DOMAIN/assets/app.css")"
test "$status" = "200"
grep -Fq '.save-toggle' "$TEMP/style.css" || {
  echo "BLOCKED: save button stylesheet missing"; exit 1;
}
echo "MARKETPLACE_SORT_AND_CARD_ASSETS=VERIFIED"

echo "========== VERIFY PRIVATE WORKSPACE GUEST GUARDS =========="
for page in "/account/saved" "/account/creator/insights"; do
  status="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 \
    --resolve "$DOMAIN:443:127.0.0.1" \
    -D "$TEMP/headers" -o "$TEMP/body" -w '%{http_code}' "https://$DOMAIN$page")"
  test "$status" = "302" || {
    echo "BLOCKED: $page responded HTTP $status"; exit 1;
  }
  grep -Eiq '^location: (https://[^/]*)?/login([[:space:]]|$)' "$TEMP/headers" || {
    echo "BLOCKED: unexpected guest redirect from $page"; exit 1;
  }
done
echo "PRIVATE_ACCOUNT_ACCESS_GUARDS=VERIFIED"

echo "========== RECONCILIATION COMPLETED =========="
echo "SAVED_PROMPTS_AND_CREATOR_INSIGHTS=SOURCE_VERIFIED"
echo "PROMPT_SAVES_MIGRATION=INSTALLED"
echo "POST_UPGRADE_V4_INVENTORY=$POST"
echo "BACKUP_ENCRYPTED_V3=PRESERVED"
echo "PRODUCTION_SOURCE_FILES=UNCHANGED"
echo "PAYMENT_NGINX_ENV=UNCHANGED"
echo "OFFSITE_RESTORATION=NOT_VERIFIED"
echo "NEXT=Perform authenticated creator/admin/customer browser acceptance"
