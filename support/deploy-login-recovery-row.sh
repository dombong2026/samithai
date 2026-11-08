#!/usr/bin/env bash
# Samith AI — minimal production login row update.
# --check is read-only. --apply replaces only login.blade.php and app.css, with automatic rollback.
set -Eeuo pipefail
umask 077
if [[ "$#" -ne 1 || ( "$1" != "--check" && "$1" != "--apply" ) ]]; then
  echo 'Usage: bash support/deploy-login-recovery-row.sh --check|--apply' >&2
  exit 2
fi
MODE="$1"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
COMMIT="586a6af48445a559ef7918b81f3bd85ab1889ff3"
PHP="/www/server/php/85/bin/php"
DOMAIN="samithai.com"
FILES="$(cat <<'FILESPEC'
resources/views/auth/login.blade.php|dcaea285d23ae49d52c8b0a2b7012a82b1c52667|93ba08a0c8564e75ee6fc0ddb0fae9182682f5ae
public/assets/app.css|dcfcbbedcff48626a25b836afb9c22cb0f9c85d3|681ea1b7963121794a478978fcdd67b01b6302b1
FILESPEC
)"

echo '========== PRODUCTION LOGIN ROW PRECHECK =========='
test "$(id -u)" -eq 0
command -v git >/dev/null
command -v curl >/dev/null
command -v flock >/dev/null
test -x "$PHP"
test -f "$APP/artisan"
test -f "$APP/storage/app/installed.lock"
test -f "$APP/.env"
id www >/dev/null

# Avoid concurrent operators deploying the same UI patch.
exec 9>/root/samithai-login-recovery-row.lock
flock -n 9 || { echo 'BLOCKED: another login patch is already running'; exit 1; }

while IFS='|' read -r path before after; do
  target="$APP/$path"
  test -f "$target"
  actual="$(git hash-object "$target")"
  if [[ "$actual" != "$before" ]]; then
    echo "BLOCKED: production file differs from the expected version: $path"
    echo 'No existing production files were changed.'
    exit 1
  fi
done <<< "$FILES"

echo 'EXISTING_LOGIN_AND_CSS=VERIFIED'
if [[ "$MODE" == "--check" ]]; then
  echo 'PATCH_READY=YES'
  echo 'PRODUCTION_FILES=UNCHANGED'
  exit 0
fi

echo '========== FETCH PINNED TWO-FILE PATCH =========='
WORK="$(mktemp -d /root/samithai-login-row-source.XXXXXXXX)"
trap 'rm -rf "$WORK"' EXIT
while IFS='|' read -r path before after; do
  mkdir -p "$WORK/$(dirname "$path")"
  curl -fsSL --proto '=https' --connect-timeout 8 --max-time 35 \
    "https://raw.githubusercontent.com/dombong2026/samithai/$COMMIT/$path" \
    -o "$WORK/$path"
  test "$(git hash-object "$WORK/$path")" = "$after"
done <<< "$FILES"
echo 'PATCH_FILE_HASHES=VERIFIED'

echo '========== SAVE ROLLBACK COPIES =========='
BACKUP="$(mktemp -d /root/samithai-login-row-backup.XXXXXXXX)"
while IFS='|' read -r path before after; do
  mkdir -p "$BACKUP/$(dirname "$path")"
  cp -p "$APP/$path" "$BACKUP/$path"
done <<< "$FILES"
echo "ROLLBACK_BACKUP=$BACKUP"

MODIFIED=0
rollback() {
  code="$1"
  trap - ERR INT TERM
  if [[ "$MODIFIED" -eq 1 ]]; then
    echo 'LOGIN_ROW_UPDATE_FAILED=ROLLING_BACK'
    while IFS='|' read -r path before after; do
      cp -p "$BACKUP/$path" "$APP/$path"
    done <<< "$FILES"
    (cd "$APP" && runuser -u www -- "$PHP" artisan view:clear) || true
    echo 'LOGIN_ROW_ROLLBACK=COMPLETE'
  fi
  exit "$code"
}
trap 'rollback $?' ERR
trap 'rollback 130' INT
trap 'rollback 143' TERM

echo '========== DEPLOY TWO FILES ONLY =========='
MODIFIED=1
while IFS='|' read -r path before after; do
  install -o root -g www -m 0640 "$WORK/$path" "$APP/$path"
  test "$(git hash-object "$APP/$path")" = "$after"
done <<< "$FILES"

echo '========== VERIFY LARAVEL VIEWS =========='
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan view:clear
  runuser -u www -- "$PHP" artisan view:cache
)

echo '========== VERIFY LIVE ORIGIN =========='
CODE="$(curl -ksS --noproxy '*' --connect-timeout 5 --max-time 20 \
  --resolve "$DOMAIN:443:127.0.0.1" \
  -o "$WORK/login.html" -w '%{http_code}' "https://$DOMAIN/login")"
test "$CODE" = '200'
grep -Fq 'class="flex between auth-options"' "$WORK/login.html"
grep -Fq 'class="tiny auth-recovery-link"' "$WORK/login.html"
grep -Fq 'eye-icon-show' "$WORK/login.html"
CODE="$(curl -ksS --noproxy '*' --connect-timeout 5 --max-time 20 \
  --resolve "$DOMAIN:443:127.0.0.1" \
  -o "$WORK/live.css" -w '%{http_code}' "https://$DOMAIN/assets/app.css")"
test "$CODE" = '200'
grep -Fq '.auth-options .auth-recovery-link' "$WORK/live.css"

trap - ERR INT TERM
echo '========== PRODUCTION LOGIN ROW UPDATE SUCCESS =========='
echo 'FORGOT_PASSWORD_LOCATION=REMEMBER_ME_ROW'
echo 'SIGN_IN_BUTTON=UNCHANGED'
echo 'PASSWORD_EYE_ICON=PRESERVED'
echo 'LOGIN_PAGE_HTTP=200'
echo 'STYLESHEET_HTTP=200'
echo 'DATABASE=UNCHANGED'
echo 'NGINX=UNCHANGED'
echo 'ENVIRONMENT=UNCHANGED'
echo "ROLLBACK_BACKUP=$BACKUP"
