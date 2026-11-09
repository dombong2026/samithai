#!/usr/bin/env bash
# Samith AI: optional frontend page visibility upgrade.
# --check is read-only. --apply installs pinned source files with exact hashes.
# No migrations, DB restore, payments, nginx or .env modifications.
set +x
set -Eeuo pipefail
umask 077

if [[ $# -ne 1 || ( "$1" != "--check" && "$1" != "--apply" ) ]]; then
  echo "Usage: bash deploy-frontend-page-visibility.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
SOURCE="63438ce2845ff8ad7599c37d5110dc76dbb85150"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
PHP="/www/server/php/85/bin/php"
DOMAIN="samithai.com"
VHOST="/www/server/panel/vhost/nginx/$DOMAIN.conf"
FILES="$(cat <<'FILEHASHES'
app/Http/Controllers/Dashboard/AppearanceController.php|e5e5705a075da1edcb0657286bf34312780d9ace|982df5ae5948c57221c825054d4d8ccac8a72a94
app/Http/Controllers/Dashboard/FrontendPageController.php|ea1d70ee995c246e14967ed455ce8bb2dc4d45d8|93fc7009bb7255647c056281ad79b65208215b8b
app/Http/Middleware/EnsureFrontendPageEnabled.php|-|9408e6f303885c82b58ae5a289b038941a16fb96
app/Support/FrontendPageAvailability.php|-|94b927369b0c36a52c2ebf85af8eae5d64a5ccb0
public/assets/app.css|dc8d9418d1a37f3776caa2d32b77782b864e8115|816b8639a8ca61925b5105b3169e01a33e3fe271
resources/views/auth/login.blade.php|ef4f79960e39e40f44f5718daa64d322779eb394|df8d9e94f8603d932152dd667918a53ced3f4e1a
resources/views/components/prompt-card.blade.php|4ba0b5be8138469055472df2b31c023a6cd7d0a6|6fd181b4091a17205f3b55c9c25c2a42f6b2f61f
resources/views/dashboard/appearance.blade.php|729749ddb1d16e4e4c3e194c9d015356041446ba|9e0c3ac841bfe5a2ee281984bba28dcb34038d0f
resources/views/layouts/site.blade.php|3d44786d486c7da38f094209e2ac34864a7a7add|8f567ddac830fa0cf074d55ab83703e374bd17a7
resources/views/layouts/workspace.blade.php|b6c7608686e8ffd15e0eae6b43532438012607c8|a5696783fcbc73a81daac2bbb4a3ddb18398624a
resources/views/marketplace/category.blade.php|55a74f42bb0ea39974847b3ae43e783cac0453c0|f6ca5fdc1ffee036ef1e3ba21d79e8a41e3bf127
resources/views/marketplace/home.blade.php|ef0448927398e798957fa2a2b0a9ba4a6a262121|1a7adb65e9686496b3f5bb55ef3a8fa2353fdf7b
resources/views/marketplace/posts.blade.php|c971512bf8d1af589dae3e74caba77e3b8fee815|7a768a59b11b7ec340fdcf7cab92bf7e5a4f5814
resources/views/marketplace/show.blade.php|6bb68a7f347b17bcf664f5d59acb2d988ef4e2aa|3a7497319e540913ee8d96940ca3c214205d9aee
resources/views/support/center.blade.php|00ad87d69b1da611c63517c43595c0033ec3b69f|d0df0560ac7271eecdb470042e72035b5b18e874
routes/web.php|b7852bd826e02b7f78b64e6ed2b0cafdb9356e8a|36f8d9c5aadc8eb1eaf46a61303778793a5bcd11
FILEHASHES
)"

echo "========== FRONTEND PAGE VISIBILITY PREFLIGHT =========="
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
if [[ "$BEFORE" -eq 16 && "$AFTER" -eq 0 ]]; then
  SOURCE_STATE=PREVIOUS
elif [[ "$BEFORE" -eq 0 && "$AFTER" -eq 16 ]]; then
  SOURCE_STATE=ALREADY_UPDATED
else
  echo "BLOCKED=MIXED_APPLICATION_FILES";echo "PREVIOUS=$BEFORE TARGET=$AFTER";exit 1
fi
echo "SOURCE_FILES=16_OF_16_VERIFIED"
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
  echo "VISIBILITY_PREFLIGHT=PASSED"
  echo "PRODUCTION_AND_DATABASE=UNCHANGED"
  exit 0
fi

exec 9>/root/samithai-frontend-page-visibility.lock
flock -n 9 || { echo "BLOCKED=ANOTHER_VISIBILITY_DEPLOYMENT";exit 1; }
WORK="$(mktemp -d /root/samithai-visibility-src.XXXXXXXX)"
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

  BACKUP="$(mktemp -d /root/samithai-frontend-page-visibility-backup.XXXXXXXX)"
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
 "dashboard.appearance.frontend.visibility":"PUT",
 "dashboard.appearance.frontend.update":"PUT",
 "dashboard.appearance.frontend.reset":"DELETE",
 "register.submit":"POST",
}
for name, method in required.items():
 found=[r for r in routes if r.get("name")==name and method in str(r.get("method","")).split("|")]
 if not found: raise SystemExit("BLOCKED=MISSING_OR_INVALID_ROUTE:"+name)
print("FRONTEND_VISIBILITY_ROUTES=VERIFIED")
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
grep -Fq '.frontend-visibility-button' "$WORK/style.css"
echo "PUBLIC_ORIGIN_AND_GUEST_GUARDS=VERIFIED"
echo "FRONTEND_PAGE_VISIBILITY_UPGRADE=SUCCESS"
echo "OPTIONAL_PAGE_SWITCHES=INSTALLED"
echo "ESSENTIAL_AND_LEGAL_PAGES=PROTECTED"
echo "CUSTOM_PAGE_PUBLISHING=REUSED"
echo "DATABASE_MIGRATIONS=NONE"
echo "PAYMENT_NGINX_ENV=UNCHANGED"
if [[ -n "$BACKUP" ]]; then echo "BACKUP_DIRECTORY=$BACKUP"; fi
echo "NEXT=Open Appearance and verify Enable/Disable as an administrator"
