#!/usr/bin/env bash
# Samith AI — User Account & Creator Workspace UI-only upgrade.
# --check read-only; --apply verifies, backs up and installs precisely three files.
# No database changes, route changes, new permissions or Nginx modifications.
set -Eeuo pipefail
set +x
umask 077

if [[ $# -ne 1 || ( "$1" != "--check" && "$1" != "--apply" ) ]]; then
  echo "Usage: bash deploy-account-creator-navigation.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
SOURCE="960b2fcf126bef85779b0c1987bc502e392f12f6"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
PHP="/www/server/php/85/bin/php"
DOMAIN="samithai.com"
VHOST="/www/server/panel/vhost/nginx/$DOMAIN.conf"
FILES="$(cat <<'RELEASE_HASHES'
resources/views/layouts/workspace.blade.php|3cedfe4c6ddbaaa89ab5cce822db4965b5bfc65c|b6c7608686e8ffd15e0eae6b43532438012607c8
resources/views/account/index.blade.php|fbf87664274e4835e4bf9967268352763566701a|008d5fc67829983a626af68fc9f9e2d1ca19a788
public/assets/app.css|5b1c25a9da08999974680b7cf571cf351a12f4df|aaf2bb751d6bbc85b77d3cde5ac3a1676fb36d80
RELEASE_HASHES
)"

echo "========== ACCOUNT & CREATOR UI PRECHECK =========="
test "$(id -u)" -eq 0
for command_name in git curl runuser flock; do command -v "$command_name" >/dev/null; done
test -x "$PHP" && test -f "$APP/artisan" && test -f "$APP/vendor/autoload.php"
test -f "$APP/.env" && test -f "$APP/storage/app/installed.lock"
test -f "$VHOST"
id www >/dev/null
grep -Fq "root $APP/public;" "$VHOST" || {
  echo "BLOCKED: Nginx root does not match installation"; exit 1
}
if [[ -e "$APP/storage/framework/down" || -e "$APP/storage/framework/maintenance.php" ]]; then
  echo "BLOCKED: production is in maintenance mode"; exit 1
fi
BEFORE=0
AFTER=0
while IFS='|' read -r path previous target; do
  destination="$APP/$path"
  test -f "$destination" && test ! -L "$destination" || {
    echo "BLOCKED: missing or symlink: $path"; exit 1
  }
  actual="$(git hash-object "$destination")"
  if [[ "$actual" == "$previous" ]]; then
    BEFORE=$((BEFORE+1))
  elif [[ "$actual" == "$target" ]]; then
    AFTER=$((AFTER+1))
  else
    echo "BLOCKED: custom or modified production file: $path"
    echo "ACTUAL_GIT_SHA=$actual"
    exit 1
  fi
done <<< "$FILES"
if [[ "$BEFORE" -eq 3 && "$AFTER" -eq 0 ]]; then
  STATE="PREVIOUS"
elif [[ "$BEFORE" -eq 0 && "$AFTER" -eq 3 ]]; then
  STATE="ALREADY_UPDATED"
else
  echo "BLOCKED: mixed previous/new file state ($BEFORE previous, $AFTER new)"; exit 1
fi
echo "SOURCE_FILES=3_OF_3_VERIFIED"
echo "SOURCE_STATE=$STATE"

if [[ "$MODE" == "--check" ]]; then
  echo "ACCOUNT_CREATOR_UI_PREFLIGHT=PASSED"
  echo "APPLICATION_DATABASE=UNCHANGED"
  echo "NO_DATABASE_MIGRATIONS_REQUIRED=YES"
  exit 0
fi

exec 9>/root/samithai-account-creator-ui.lock
flock -n 9 || { echo "BLOCKED: another account UI deployment is running"; exit 1; }
WORK="$(mktemp -d /root/samithai-account-ui-source.XXXXXXXX)"
BACKUP=""
CHANGED=0

cleanup() {
  code=$?
  trap - EXIT INT TERM
  set +e
  if [[ "$code" -ne 0 && "$CHANGED" -eq 1 ]]; then
    echo "ACCOUNT_UI_DEPLOY_FAILED=RESTORING_PREVIOUS_UI"
    while IFS='|' read -r path previous target; do
      cp -p "$BACKUP/$path" "$APP/$path"
    done <<< "$FILES"
    (
      cd "$APP"
      runuser -u www -- "$PHP" artisan view:clear --no-ansi
      runuser -u www -- "$PHP" artisan view:cache --no-ansi
    ) >/dev/null 2>&1
    echo "PREVIOUS_UI_ROLLBACK=ATTEMPTED"
    echo "ROLLBACK_BACKUP_DIRECTORY=$BACKUP"
  fi
  rm -rf -- "$WORK"
  exit "$code"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ "$STATE" == "PREVIOUS" ]]; then
  echo "========== DOWNLOAD AND VERIFY THREE PINNED UI FILES =========="
  while IFS='|' read -r path previous target; do
    mkdir -p "$WORK/$(dirname "$path")"
    curl -fsSL --retry 3 --proto '=https' --connect-timeout 10 --max-time 60 \
      "https://raw.githubusercontent.com/dombong2026/samithai/$SOURCE/$path" \
      -o "$WORK/$path"
    if [[ "$(git hash-object "$WORK/$path")" != "$target" ]]; then
      echo "BLOCKED: pinned Git source mismatch: $path"; exit 1
    fi
  done <<< "$FILES"
  echo "PINNED_GITHUB_SOURCE=VERIFIED"

  BACKUP="$(mktemp -d /root/samithai-account-creator-ui-backup.XXXXXXXX)"
  while IFS='|' read -r path previous target; do
    mkdir -p "$BACKUP/$(dirname "$path")"
    cp -p "$APP/$path" "$BACKUP/$path"
  done <<< "$FILES"
  echo "ROLLBACK_BACKUP_DIRECTORY=$BACKUP"

  echo "========== INSTALL SCOPED ACCOUNT AND CREATOR UI =========="
  CHANGED=1
  while IFS='|' read -r path previous target; do
    install -o root -g www -m 0644 "$WORK/$path" "$APP/$path"
    test "$(git hash-object "$APP/$path")" = "$target"
  done <<< "$FILES"
  (
    cd "$APP"
    runuser -u www -- "$PHP" artisan view:clear --no-ansi
    runuser -u www -- "$PHP" artisan view:cache --no-ansi
    runuser -u www -- "$PHP" artisan route:list --except-vendor --no-ansi >/dev/null
  )
  echo "WORKSPACE_VIEWS_AND_ROUTES=VERIFIED"
else
  echo "UI_ALREADY_UPDATED=NO_SOURCE_REPLACEMENT"
fi

echo "========== VERIFY LIVE PUBLIC ORIGIN AND AUTH GUARDS =========="
for page in "/" "/login" "/prompts"; do
  status="$(curl -ksS --noproxy '*' --connect-timeout 8 --max-time 25 \
    --resolve "$DOMAIN:443:127.0.0.1" \
    -o "$WORK/page" -w '%{http_code}' "https://$DOMAIN$page")"
  test "$status" = "200" || {
    echo "BLOCKED: public origin $page returned $status"; exit 1
  }
done
for page in "/account" "/account/saved" "/account/creator/insights" "/dashboard"; do
  status="$(curl -ksS --noproxy '*' --connect-timeout 8 --max-time 25 \
    --resolve "$DOMAIN:443:127.0.0.1" \
    -D "$WORK/headers" -o "$WORK/page" \
    -w '%{http_code}' "https://$DOMAIN$page")"
  test "$status" = "302" || {
    echo "BLOCKED: guest access status $status for $page"; exit 1
  }
  grep -Eiq '^location: (https://[^/]*)?/login([[:space:]]|$)' "$WORK/headers" || {
    echo "BLOCKED: private route does not redirect to sign-in: $page"; exit 1
  }
done
echo "GUEST_ACCOUNT_CREATOR_AND_ADMIN_GUARDS=VERIFIED"
status="$(curl -ksS --noproxy '*' --connect-timeout 8 --max-time 25 \
  --resolve "$DOMAIN:443:127.0.0.1" \
  -o "$WORK/css" -w '%{http_code}' "https://$DOMAIN/assets/app.css")"
test "$status" = "200"
grep -Fq '.account-dashboard-premium' "$WORK/css"
grep -Fq '.workspace-creator-group' "$WORK/css"
echo "ACCOUNT_CREATOR_CSS=VERIFIED"

CHANGED=0
echo "========== ACCOUNT & CREATOR UI UPGRADE SUCCESS =========="
echo "CUSTOMER_MENU_GROUPS=INSTALLED"
echo "CREATOR_STUDIO_MENU_GROUP=INSTALLED"
echo "PREMIUM_ACCOUNT_DASHBOARD=INSTALLED"
echo "ADMIN_NAVIGATION=PRESERVED"
echo "MOBILE_MENU_AND_DARK_MODE=PRESERVED"
echo "DATABASE_MIGRATIONS=UNCHANGED"
echo "NGINX_ENV_PAYMENTS=UNCHANGED"
if [[ -n "$BACKUP" ]]; then echo "ROLLBACK_BACKUP_DIRECTORY=$BACKUP"; fi
echo "NEXT=Authenticated customer and creator visual acceptance"
