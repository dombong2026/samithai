#!/usr/bin/env bash
# Samith AI: restore workspace header markup and refine transparent controls, marketplace icon and locale flags.
# --check is read-only. --apply installs pinned source files with exact hashes.
# No migrations, DB restore, payments, nginx or .env modifications.
set +x
set -Eeuo pipefail
umask 077

if [[ $# -ne 1 || ( "$1" != "--check" && "$1" != "--apply" ) ]]; then
  echo "Usage: bash deploy-workspace-header-refinement.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
SOURCE="528bdff7d7d3874840f9ac70d3e1be73e1fcd323"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
PHP="/www/server/php/85/bin/php"
DOMAIN="samithai.com"
VHOST="/www/server/panel/vhost/nginx/$DOMAIN.conf"
FILES="$(cat <<'FILEHASHES'
config/samith.php|6cd9bddff2cf599b972ea445bbe320b0bcbef5b9|3d32c3bfe68bd2311886e697382b478788d5b1a6
public/assets/app.css|2023c0992b4216f9ef19bcded98ee01e8642482f|ee1e7038ffb352a7992aaea33d45d84fd95afe03
resources/views/layouts/workspace.blade.php|935e550d95cff695336e2d4d9017eab87c46b328|81ed8a0f2d8fda87314d86adb5cb83ef02736e2e
FILEHASHES
)"

echo "========== WORKSPACE HEADER REFINEMENT PREFLIGHT =========="
test "$(id -u)" -eq 0
for executable in git curl python3 runuser flock; do command -v "$executable" >/dev/null; done
test -x "$PHP"
test -f "$APP/artisan"
test -f "$APP/vendor/autoload.php"
test -f "$APP/.env"
test -f "$APP/storage/app/installed.lock"
test -f "$VHOST"
id www >/dev/null
grep -Fq "root $APP/public;" "$VHOST" || { echo "BLOCKED=NGINX_ROOT_MISMATCH";exit 1; }
if [[ -e "$APP/storage/framework/down" || -e "$APP/storage/framework/maintenance.php" ]]; then
  echo "BLOCKED=MAINTENANCE_ALREADY_ACTIVE";exit 1
fi

BEFORE=0
AFTER=0
while IFS='|' read -r path prior target; do
  file="$APP/$path"
  if [[ "$prior" == "-" && ! -e "$file" && ! -L "$file" ]]; then
    BEFORE=$((BEFORE+1))
  elif [[ -f "$file" && ! -L "$file" ]]; then
    actual="$(git hash-object "$file")"
    if [[ "$actual" == "$prior" ]]; then
      BEFORE=$((BEFORE+1))
    elif [[ "$actual" == "$target" ]]; then
      AFTER=$((AFTER+1))
    else
      echo "BLOCKED=CUSTOM_OR_UNKNOWN_SOURCE:$path";echo "ACTUAL_SHA=$actual";exit 1
    fi
  else
    echo "BLOCKED=MISSING_OR_SYMLINK_SOURCE:$path";exit 1
  fi
done <<< "$FILES"
if [[ "$BEFORE" -eq 3 && "$AFTER" -eq 0 ]]; then
  SOURCE_STATE=PREVIOUS
elif [[ "$BEFORE" -eq 0 && "$AFTER" -eq 3 ]]; then
  SOURCE_STATE=ALREADY_UPDATED
else
  echo "BLOCKED=MIXED_APPLICATION_FILES";echo "PREVIOUS=$BEFORE TARGET=$AFTER";exit 1
fi
echo "SOURCE_FILES=3_OF_3_VERIFIED"
echo "SOURCE_STATE=$SOURCE_STATE"
(
 cd "$APP"
 runuser -u www -- "$PHP" -r '
  require "vendor/autoload.php";
  $app=require "bootstrap/app.php";
  $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
  if (config("database.default") !== "mysql"
      || !\Illuminate\Support\Facades\Schema::hasTable("site_settings")
      || !\Illuminate\Support\Facades\Schema::hasTable("audit_events")
      || !\Illuminate\Support\Facades\Schema::hasTable("content_entries")) {
    fwrite(STDERR, "BLOCKED=REQUIRED_DATABASE_TABLES_MISSING\n");exit(1);
  }
  echo "DATABASE_REUSED=VERIFIED\n";
 '
)
if [[ "$MODE" == "--check" ]]; then
  echo "HEADER_REFINEMENT_PREFLIGHT=PASSED"
  echo "PRODUCTION_AND_DATABASE=UNCHANGED"
  exit 0
fi

exec 9>/root/samithai-workspace-header-refinement.lock
flock -n 9 || { echo "BLOCKED=ANOTHER_HEADER_DEPLOYMENT";exit 1; }
WORK="$(mktemp -d /root/samithai-workspace-refinement-src.XXXXXXXX)"
BACKUP=""
WRITTEN=0
MAINT=0
finish() {
  code=$?
  trap - EXIT INT TERM
  set +e
  if [[ "$code" -ne 0 && "$WRITTEN" -eq 1 ]]; then
    echo "ROLLBACK=STARTED"
    if [[ "$MAINT" -eq 0 ]]; then
      if (cd "$APP"; runuser -u www -- "$PHP" artisan down --retry=60 --no-ansi) >/dev/null 2>&1; then MAINT=1; fi
    fi
    recovered=1
    while IFS='|' read -r path prior target; do
      if [[ "$prior" == "-" ]]; then
        rm -f -- "$APP/$path" || recovered=0
      else
        cp -p -- "$BACKUP/$path" "$APP/$path" || recovered=0
      fi
    done <<< "$FILES"
    while IFS='|' read -r path prior target; do
      if [[ "$prior" == "-" ]]; then
        [[ ! -e "$APP/$path" && ! -L "$APP/$path" ]] || { echo "ROLLBACK_MISMATCH=$path"; recovered=0; }
      else
        [[ -f "$APP/$path" && ! -L "$APP/$path" ]] && [[ "$(git hash-object "$APP/$path" 2>/dev/null)" == "$prior" ]] || { echo "ROLLBACK_MISMATCH=$path"; recovered=0; }
      fi
    done <<< "$FILES"
    if [[ "$recovered" -eq 1 ]]; then echo "SOURCE_ROLLBACK=VERIFIED";else echo "SOURCE_ROLLBACK=FAILED";fi
    if (cd "$APP"; runuser -u www -- "$PHP" artisan route:clear --no-ansi; runuser -u www -- "$PHP" artisan view:clear --no-ansi; runuser -u www -- "$PHP" artisan view:cache --no-ansi) >"$BACKUP/rollback-cache.log" 2>&1; then
      echo "ROLLBACK_CACHES=REBUILT"
    else
      echo "ROLLBACK_CACHES=FAILED"
      echo "CACHE_LOG=$BACKUP/rollback-cache.log"
    fi
    echo "BACKUP_DIRECTORY=$BACKUP"
  fi
  if [[ "$MAINT" -eq 1 ]]; then
    if (cd "$APP"; runuser -u www -- "$PHP" artisan up --no-ansi) >/dev/null 2>&1 && [[ ! -e "$APP/storage/framework/down" && ! -e "$APP/storage/framework/maintenance.php" ]]; then
      echo "MAINTENANCE_EXIT=VERIFIED"
    else
      echo "MAINTENANCE_EXIT=FAILED"
    fi
  fi
  rm -rf -- "$WORK"
  exit "$code"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ "$SOURCE_STATE" == "PREVIOUS" ]]; then
  echo "========== VERIFY PINNED GITHUB BLOBS =========="
  while IFS='|' read -r path prior target; do
    mkdir -p "$WORK/$(dirname "$path")"
    curl -fsSL --retry 3 --proto '=https' --connect-timeout 10 --max-time 60 \
      "https://raw.githubusercontent.com/dombong2026/samithai/$SOURCE/$path" -o "$WORK/$path"
    test "$(git hash-object "$WORK/$path")" = "$target" || { echo "BLOCKED=SOURCE_HASH_MISMATCH:$path";exit 1; }
    if [[ "$path" == *.php && "$path" != *.blade.php ]]; then "$PHP" -l "$WORK/$path" >/dev/null; fi
  done <<< "$FILES"
  echo "PINNED_SOURCE_BLOBS_AND_PHP_SYNTAX=VERIFIED"

  BACKUP="$(mktemp -d /root/samithai-workspace-refinement-backup.XXXXXXXX)"
  while IFS='|' read -r path prior target; do
    if [[ "$prior" != "-" ]]; then
      mkdir -p "$BACKUP/$(dirname "$path")"
      cp -p -- "$APP/$path" "$BACKUP/$path"
    fi
  done <<< "$FILES"
  echo "BACKUP_DIRECTORY=$BACKUP"

  echo "========== INSTALL APPLICATION PATCH =========="
  MAINT=1
  (cd "$APP"; runuser -u www -- "$PHP" artisan down --retry=60 --no-ansi)
  WRITTEN=1
  while IFS='|' read -r path prior target; do
    install -o root -g www -m 0644 "$WORK/$path" "$APP/$path"
    test "$(git hash-object "$APP/$path")" = "$target"
  done <<< "$FILES"
  (
    cd "$APP"
    runuser -u www -- "$PHP" artisan route:clear --no-ansi
    runuser -u www -- "$PHP" artisan view:clear --no-ansi
    runuser -u www -- "$PHP" artisan view:cache --no-ansi
    runuser -u www -- "$PHP" artisan route:list --except-vendor --json --no-ansi > "$WORK/routes.json"
  )
  python3 - "$WORK/routes.json" <<'PYTHON_ROUTES'
import json
import sys
from pathlib import Path
routes=json.loads(Path(sys.argv[1]).read_text())
required={
 "dashboard":"GET",
 "account":"GET",
 "dashboard.appearance":"GET",
 "logout":"POST",
}
for name, method in required.items():
 found=[r for r in routes if r.get("name")==name and method in str(r.get("method","")).split("|")]
 if not found: raise SystemExit("BLOCKED=MISSING_OR_INVALID_ROUTE:"+name)
print("WORKSPACE_HEADER_ROUTES=VERIFIED")
PYTHON_ROUTES
  (cd "$APP"; runuser -u www -- "$PHP" artisan up --no-ansi)
  MAINT=0
else
  echo "TARGET_FILES_ALREADY_PRESENT=SKIPPING_WRITE"
fi

echo "========== PUBLIC AND PRIVATE ORIGIN CHECKS =========="
for path in "/" "/login" "/policies"; do
  http="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 --resolve "$DOMAIN:443:127.0.0.1" -o "$WORK/response" -w '%{http_code}' "https://$DOMAIN$path")"
  test "$http" = "200" || { echo "BLOCKED=PUBLIC_ROUTE:$path:HTTP_$http";exit 1; }
done
for path in "/dashboard" "/dashboard/appearance" "/account"; do
  http="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 --resolve "$DOMAIN:443:127.0.0.1" -D "$WORK/headers" -o "$WORK/response" -w '%{http_code}' "https://$DOMAIN$path")"
  test "$http" = "302" || { echo "BLOCKED=PRIVATE_GUEST_GUARD:$path:HTTP_$http";exit 1; }
  grep -Eiq '^location: (https://[^/]*)?/login([[:space:]]|$)' "$WORK/headers" || { echo "BLOCKED=PRIVATE_LOGIN_REDIRECT:$path";exit 1; }
done
css="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 --resolve "$DOMAIN:443:127.0.0.1" -o "$WORK/style.css" -w '%{http_code}' "https://$DOMAIN/assets/app.css")"
test "$css" = "200"
grep -Fq '.workspace-header-search' "$WORK/style.css"
grep -Fq '.workspace-sidebar-toggle' "$WORK/style.css"
grep -Fq '.workspace-language-flag' "$WORK/style.css"
grep -Fq '.workspace-language-trigger' "$WORK/style.css"
js="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 --resolve "$DOMAIN:443:127.0.0.1" -o "$WORK/script.js" -w '%{http_code}' "https://$DOMAIN/assets/app.js")"
test "$js" = "200"
grep -Fq 'Workspace header navigation enhancements' "$WORK/script.js"
echo "PUBLIC_ORIGIN_AND_GUEST_GUARDS=VERIFIED"
echo "WORKSPACE_HEADER_REFINEMENT=SUCCESS"
echo "STRAY_HEADER_CHARACTER=REMOVED"
echo "TRANSPARENT_HEADER_CONTROLS=INSTALLED"
echo "MARKETPLACE_SVG_ICON_ONLY=INSTALLED"
echo "LANGUAGE_FLAGS_13_LOCALES=INSTALLED"
echo "PROFILE_ICON_SURFACE=PRESERVED"
echo "DATABASE_MIGRATIONS=NONE"
echo "PAYMENT_NGINX_ENV=UNCHANGED"
if [[ -n "$BACKUP" ]]; then echo "BACKUP_DIRECTORY=$BACKUP"; fi
echo "NEXT=Verify toolbar appearance, language flags, marketplace icon and sidebar on desktop/mobile"
