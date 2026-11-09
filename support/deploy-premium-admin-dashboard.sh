#!/usr/bin/env bash
# Samith AI administration dashboard UI-only patch.
# --check is read-only. --apply installs exactly six reviewed files.
# Already-upgraded source is accepted and verified without recopying.
# No migrations, schema changes, Nginx changes, or payment changes.
set +x
set -Eeuo pipefail
umask 077

if [[ $# -ne 1 || ( "$1" != "--check" && "$1" != "--apply" ) ]]; then
  echo "Usage: bash support/deploy-premium-admin-dashboard.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
SOURCE="16e39e2d15c75b8c9e33e8581cd61ab74ad88129"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
PHP="/www/server/php/85/bin/php"
DOMAIN="samithai.com"
VHOST="/www/server/panel/vhost/nginx/$DOMAIN.conf"
EVIDENCE="/root/SamithAI-Upgrade-Evidence"
ENC="$EVIDENCE/samithai-current-v3-20261109T062845Z.tar.gpg"
FILES="$(cat <<'EXPECTED_HASHES'
app/Http/Controllers/Dashboard/OverviewController.php|916a25f305eafd0b1cd41c13ed1dd9f68463f449|ca745b5429b2d8a437c0b1e4818b0f5c576c626c
public/assets/app.css|287206fe55fb03d0a8a1857edd4c5a3d1f8a9b41|5b1c25a9da08999974680b7cf571cf351a12f4df
public/assets/app.js|1c961f4aec17051816ba345527a604017d1b4240|69af028b80ad55aeac940668bc833c4bb4cc2c8f
resources/views/components/nav-icon.blade.php|-|a59d210cce70f03dd9db3020e7c73ef625f8b9bb
resources/views/dashboard/overview.blade.php|58349229fd1cd85b71e081e15363e3483b547285|5dbfab3cebc7f4dee83ab819a433ca5634e9b133
resources/views/layouts/workspace.blade.php|8617ab0195283f671cb88b2950d4718d0b09195e|3cedfe4c6ddbaaa89ab5cce822db4965b5bfc65c
EXPECTED_HASHES
)"

echo "========== SAMITH AI PREMIUM ADMIN DASHBOARD PREFLIGHT =========="
test "$(id -u)" -eq 0
for cmd in git curl sha256sum runuser flock; do command -v "$cmd" >/dev/null; done
test -x "$PHP"
test -f "$APP/artisan" && test -f "$APP/vendor/autoload.php"
test -f "$APP/.env" && test -f "$APP/storage/app/installed.lock"
test -f "$VHOST" && test -s "$ENC" && test -s "$ENC.sha256"
test ! -L "$ENC" && test ! -L "$ENC.sha256"
id www >/dev/null
grep -Fq "root $APP/public;" "$VHOST" || { echo "BLOCKED: unexpected Nginx root"; exit 1; }
if [[ -e "$APP/storage/framework/down" || -e "$APP/storage/framework/maintenance.php" ]]; then
  echo "BLOCKED: existing maintenance mode"; exit 1
fi
(
  cd "$EVIDENCE"
  sha256sum --quiet -c "$(basename "$ENC").sha256"
)
echo "CURRENT_ENCRYPTED_BACKUP=CHECKSUM_VERIFIED"

CURRENT=0
TARGET=0
while IFS='|' read -r path before after; do
  dest="$APP/$path"
  if [[ "$before" == "-" && ! -e "$dest" && ! -L "$dest" ]]; then
    CURRENT=$((CURRENT+1))
  elif [[ -f "$dest" && ! -L "$dest" ]]; then
    actual="$(git hash-object "$dest")"
    if [[ "$actual" == "$before" ]]; then
      CURRENT=$((CURRENT+1))
    elif [[ "$actual" == "$after" ]]; then
      TARGET=$((TARGET+1))
    else
      echo "BLOCKED: custom or unexpected file: $path"
      echo "ACTUAL_HASH=$actual"
      exit 1
    fi
  else
    echo "BLOCKED: missing, symlink or unexpected path: $path"
    exit 1
  fi
done <<< "$FILES"
if [[ "$CURRENT" -eq 6 && "$TARGET" -eq 0 ]]; then
  SOURCE_STATE="PREVIOUS"
elif [[ "$TARGET" -eq 6 && "$CURRENT" -eq 0 ]]; then
  SOURCE_STATE="ALREADY_UPDATED"
else
  echo "BLOCKED: mixed source state ($CURRENT old, $TARGET new)"
  exit 1
fi
echo "SOURCE_FILE_HASHES=6_OF_6_VERIFIED"
echo "SOURCE_STATE=$SOURCE_STATE"

DB="$(
 cd "$APP"
 runuser -u www -- "$PHP" -r '
   require "vendor/autoload.php";
   $app=require "bootstrap/app.php";
   $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
   if (config("database.default")!=="mysql"
       || !\Illuminate\Support\Facades\Schema::hasTable("prompt_reviews")
       || !\Illuminate\Support\Facades\Schema::hasTable("prompt_saves")
       || !\Illuminate\Support\Facades\DB::table("migrations")
          ->where("migration","2026_11_09_020000_create_prompt_saves_table")->exists()) {
      fwrite(STDERR,"BLOCKED: expected installed prompt_saves schema\n");
      exit(1);
   }
   echo "OK";
 '
)"
test "$DB" = "OK"
echo "LIVE_SAVED_PROMPTS_SCHEMA=VERIFIED"

if [[ "$MODE" == "--check" ]]; then
  echo "ADMIN_DASHBOARD_PREFLIGHT=PASSED"
  echo "APPLICATION_FILES_AND_DATABASE=UNCHANGED"
  echo "NO_MIGRATIONS_REQUIRED=YES"
  exit 0
fi

exec 9>/root/samithai-premium-admin-dashboard.lock
flock -n 9 || { echo "BLOCKED: another dashboard deployment is running"; exit 1; }
WORK="$(mktemp -d /root/samithai-admin-ui-source.XXXXXXXX)"
BACKUP=""
CHANGED=0

finish() {
  result=$?
  trap - EXIT INT TERM
  set +e
  if [[ "$result" -ne 0 && "$CHANGED" -eq 1 ]]; then
    echo "ADMIN_DASHBOARD_DEPLOY_FAILED=RESTORING_PREVIOUS_UI"
    while IFS='|' read -r path before after; do
      if [[ "$before" == "-" ]]; then
        rm -f -- "$APP/$path"
      else
        cp -p -- "$BACKUP/$path" "$APP/$path"
      fi
    done <<< "$FILES"
    (
      cd "$APP"
      runuser -u www -- "$PHP" artisan view:clear --no-ansi
      runuser -u www -- "$PHP" artisan view:cache --no-ansi
    ) >/dev/null 2>&1
    echo "UI_FILE_ROLLBACK=ATTEMPTED"
    echo "ROLLBACK_BACKUP_DIRECTORY=$BACKUP"
  fi
  rm -rf -- "$WORK"
  exit "$result"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ "$SOURCE_STATE" == "PREVIOUS" ]]; then
  echo "========== FETCH EXACT REVIEWED UI SOURCE =========="
  while IFS='|' read -r path before after; do
    mkdir -p "$WORK/$(dirname "$path")"
    curl -fsSL --retry 3 --proto '=https' --connect-timeout 10 --max-time 60 \
      "https://raw.githubusercontent.com/dombong2026/samithai/$SOURCE/$path" -o "$WORK/$path"
    test "$(git hash-object "$WORK/$path")" = "$after" || {
      echo "BLOCKED: pinned source checksum mismatch for $path"; exit 1
    }
  done <<< "$FILES"
  echo "PINNED_SOURCE_HASHES=VERIFIED"
  BACKUP="$(mktemp -d /root/samithai-premium-admin-ui-backup.XXXXXXXX)"
  while IFS='|' read -r path before after; do
    if [[ "$before" != "-" ]]; then
      mkdir -p "$BACKUP/$(dirname "$path")"
      cp -p -- "$APP/$path" "$BACKUP/$path"
    fi
  done <<< "$FILES"
  echo "ROLLBACK_BACKUP_DIRECTORY=$BACKUP"

  echo "========== INSTALL SIX SCOPED UI FILES =========="
  CHANGED=1
  while IFS='|' read -r path before after; do
    install -o root -g www -m 0644 "$WORK/$path" "$APP/$path"
    test "$(git hash-object "$APP/$path")" = "$after"
  done <<< "$FILES"
  (
    cd "$APP"
    runuser -u www -- "$PHP" artisan view:clear --no-ansi
    runuser -u www -- "$PHP" artisan view:cache --no-ansi
    runuser -u www -- "$PHP" artisan route:list --except-vendor --no-ansi >/dev/null
  )
  echo "ADMIN_DASHBOARD_VIEWS_AND_ROUTES=VERIFIED"
else
  echo "UI_SOURCE_ALREADY_INSTALLED=SKIPPING_REINSTALL"
fi

echo "========== VERIFY PUBLIC ORIGIN AND AUTH GATE =========="
for page in "/" "/login" "/prompts"; do
  status="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 \
    --resolve "$DOMAIN:443:127.0.0.1" \
    -o "$WORK/body" -w '%{http_code}' "https://$DOMAIN$page")"
  if [[ "$status" != "200" ]]; then
    echo "BLOCKED: HTTP $status for $page"; exit 1
  fi
done
status="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 \
  --resolve "$DOMAIN:443:127.0.0.1" \
  -D "$WORK/headers" -o "$WORK/body" \
  -w '%{http_code}' "https://$DOMAIN/dashboard")"
test "$status" = "302" || { echo "BLOCKED: admin guest check returned HTTP $status"; exit 1; }
grep -Eiq '^location: (https://[^/]*)?/login([[:space:]]|$)' "$WORK/headers" || {
  echo "BLOCKED: admin guest access did not redirect to login"; exit 1
}
echo "GUEST_ADMIN_ACCESS=BLOCKED_AS_EXPECTED"

status="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 \
  --resolve "$DOMAIN:443:127.0.0.1" \
  -o "$WORK/css" -w '%{http_code}' "https://$DOMAIN/assets/app.css")"
test "$status" = "200"
grep -Fq '.admin-hero-panel' "$WORK/css"
grep -Fq '.workspace-nav-group' "$WORK/css"
status="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 \
  --resolve "$DOMAIN:443:127.0.0.1" \
  -o "$WORK/js" -w '%{http_code}' "https://$DOMAIN/assets/app.js")"
test "$status" = "200"
grep -Fq 'data-workspace-mobile-nav' "$WORK/js"
echo "PREMIUM_DASHBOARD_CSS_AND_JS=VERIFIED"

CHANGED=0
echo "========== PREMIUM ADMIN DASHBOARD DEPLOYMENT PASSED =========="
echo "GROUPED_UNIQUE_ADMIN_NAVIGATION=INSTALLED"
echo "MARKETPLACE_INTEREST_LAYOUT=FIXED"
echo "REAL_OPERATIONAL_OVERVIEW=INSTALLED"
echo "MOBILE_NAVIGATION=INSTALLED"
echo "LIGHT_AND_DARK_THEME=SUPPORTED"
echo "DATABASE_AND_MIGRATIONS=UNCHANGED"
echo "NGINX_ENV_PAYMENTS=UNCHANGED"
echo "ENCRYPTED_BACKUP=PRESERVED"
if [[ -n "$BACKUP" ]]; then echo "ROLLBACK_BACKUP_DIRECTORY=$BACKUP"; fi
echo "NEXT=Authenticated administrator mobile and desktop visual acceptance"
