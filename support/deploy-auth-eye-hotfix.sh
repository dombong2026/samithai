#!/usr/bin/env bash
# Minimal Samith AI auth eye-icon fix for the already installed Laravel production release.
# --check is read-only. --apply changes only 6 UI files and verifies origin pages.
set -Eeuo pipefail
umask 077
if [[ "$#" -ne 1 || ( "$1" != '--check' && "$1" != '--apply' ) ]]; then
  echo 'Usage: deploy-auth-eye-hotfix.sh --check|--apply' >&2
  exit 2
fi
MODE="$1"
REPO="dombong2026/samithai"
SHA="077a64addc7745f09e61ed4df60c91d028b7fafc"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
PHP="/www/server/php/85/bin/php"
DOMAIN="samithai.com"
SPECS="$(cat <<'FILES'
resources/views/auth/login.blade.php|979c65314fa4a3a2d79c1c94eb969824267b3323|dcaea285d23ae49d52c8b0a2b7012a82b1c52667
resources/views/auth/register.blade.php|630e54c28d944064a422d48ef3345f5cf1fdabeb|617daa9c53a312817c6f2a5fd635f9336f811d04
resources/views/auth/reset-password.blade.php|afdc665730eae55341853b7d631ca1baa0348d1a|66347f1ae78c513db09d0351f60b7c0ee23f3968
public/assets/app.css|0708c108931c7cd539e47f0c227ef2c20a0ce7d6|dcfcbbedcff48626a25b836afb9c22cb0f9c85d3
public/assets/app.js|ecec7362503df35ad0ba23e9e37f040ff3d7c28c|1c961f4aec17051816ba345527a604017d1b4240
resources/views/partials/auth-password-toggle.blade.php|-|a9e9424995176ae49c0c14a2d056725912a87aee
FILES
)"
echo '========== PRODUCTION AUTH PRECHECK =========='
test "$(id -u)" -eq 0
command -v git >/dev/null
command -v curl >/dev/null
test -x "$PHP"
test -f "$APP/artisan"
test -f "$APP/.env"
test -f "$APP/storage/app/installed.lock"
id www >/dev/null

while IFS='|' read -r rel before after; do
  target="$APP/$rel"
  if [[ "$before" == "-" ]]; then
    if [[ -e "$target" ]]; then
      echo "BLOCKED: Unexpected existing file $rel"
      exit 1
    fi
  else
    test -f "$target"
    if [[ "$(git hash-object "$target")" != "$before" ]]; then
      echo "BLOCKED: Local file differs from original release: $rel"
      echo 'No production files were changed.'
      exit 1
    fi
  fi
done <<< "$SPECS"
echo 'EXISTING_AUTH_FILES=VERIFIED'
if [[ "$MODE" == '--check' ]]; then
  echo 'HOTFIX=READY_FOR_APPLY'
  echo 'LIVE_FILES=UNCHANGED'
  exit 0
fi

echo '========== DOWNLOAD PINNED UI PATCH =========='
WORK="$(mktemp -d /root/samithai-eye-source.XXXXXXXX)"
trap 'rm -rf "$WORK"' EXIT
while IFS='|' read -r rel before after; do
  mkdir -p "$WORK/$(dirname "$rel")"
  curl --fail --silent --show-error --location --proto '=https' \
    --connect-timeout 8 --max-time 30 \
    "https://raw.githubusercontent.com/$REPO/$SHA/$rel" \
    -o "$WORK/$rel"
  test "$(git hash-object "$WORK/$rel")" = "$after"
done <<< "$SPECS"
echo 'SIX_SOURCE_FILE_HASHES=VERIFIED'

echo '========== BACK UP CURRENT UI FILES =========='
BACKUP="$(mktemp -d /root/samithai-eye-backup.XXXXXXXX)"
while IFS='|' read -r rel before after; do
  if [[ "$before" != "-" ]]; then
    mkdir -p "$BACKUP/$(dirname "$rel")"
    cp -p "$APP/$rel" "$BACKUP/$rel"
  fi
done <<< "$SPECS"
echo "UI_BACKUP=$BACKUP"

CHANGED=0
restore() {
  code="$1"
  trap - ERR INT TERM
  if [[ "$CHANGED" == "1" ]]; then
    echo 'HOTFIX_FAILED=ROLLING_BACK'
    while IFS='|' read -r rel before after; do
      if [[ "$before" == "-" ]]; then
        rm -f "$APP/$rel"
      else
        cp -p "$BACKUP/$rel" "$APP/$rel"
      fi
    done <<< "$SPECS"
    (cd "$APP" && runuser -u www -- "$PHP" artisan view:clear) || true
    echo 'AUTH_UI=RESTORED_FROM_BACKUP'
  fi
  exit "$code"
}
trap 'restore $?' ERR
trap 'restore 130' INT
trap 'restore 143' TERM

echo '========== INSTALL UI-ONLY HOTFIX =========='
CHANGED=1
while IFS='|' read -r rel before after; do
  install -o root -g www -m 0640 "$WORK/$rel" "$APP/$rel"
  test "$(git hash-object "$APP/$rel")" = "$after"
done <<< "$SPECS"

echo '========== RECOMPILE LARAVEL VIEWS =========='
(cd "$APP" && runuser -u www -- "$PHP" artisan view:clear)
(cd "$APP" && runuser -u www -- "$PHP" artisan view:cache)

echo '========== VERIFY LOCAL AUTH PAGES =========='
for page in login register reset-password/test-only-token; do
  code="$(curl --noproxy '*' -ksS --connect-timeout 5 --max-time 20 \
    --resolve "$DOMAIN:443:127.0.0.1" \
    -o "$WORK/page.html" -w '%{http_code}' \
    "https://$DOMAIN/$page")"
  if [[ "$code" != '200' ]]; then
    echo "AUTH_PAGE_FAILED=/$page HTTP_$code"
    false
  fi
  grep -Fq 'eye-icon-show' "$WORK/page.html"
  grep -Fq 'eye-icon-hide' "$WORK/page.html"
done
for asset in app.js app.css; do
  code="$(curl --noproxy '*' -ksS --connect-timeout 5 --max-time 20 \
    --resolve "$DOMAIN:443:127.0.0.1" \
    -o "$WORK/$asset" -w '%{http_code}' \
    "https://$DOMAIN/assets/$asset")"
  test "$code" = 200
done
grep -Fq "'aria-pressed'" "$WORK/app.js"
grep -Fq '.eye-icon-hide' "$WORK/app.css"

trap - ERR INT TERM
echo '========== UI HOTFIX SUCCESS =========='
echo 'LOGIN_EYE_ICON=VERIFIED'
echo 'REGISTRATION_EYE_ICONS=VERIFIED'
echo 'RESET_EYE_ICONS=VERIFIED'
echo 'PASSWORD_TOGGLE_JS_AND_CSS=VERIFIED'
echo 'DATABASE=UNCHANGED'
echo 'NGINX=UNCHANGED'
echo 'ENVIRONMENT=UNCHANGED'
echo "ROLLBACK_BACKUP=$BACKUP"
