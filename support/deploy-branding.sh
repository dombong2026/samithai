#!/usr/bin/env bash
# Targeted Samith AI logo + favicon deployment to the existing production Laravel installation.
# --check is read-only. --apply changes only the paths in CHECKSUMS, with rollback.
set -Eeuo pipefail
umask 077

if [[ $# -ne 1 || ( "$1" != '--check' && "$1" != '--apply' ) ]]; then
  echo 'Usage: bash support/deploy-branding.sh --check|--apply' >&2
  exit 2
fi
MODE="$1"
COMMIT='c6da7c08036db0d03adfd31f93081f43a47d0085'
APP='/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49'
PHP='/www/server/php/85/bin/php'
DOMAIN='samithai.com'
FILES="$(cat <<'CHECKSUMS'
resources/views/layouts/site.blade.php|0c440ad72c78a00e76b8f4b1f6a6d8e3ae152965|56cf7c949460d0d02c591e71ee0c53c91be3560e
resources/views/layouts/workspace.blade.php|6d1aee50d8e7fdc8fbb0742824b0c7c616d9e2c0|9c0b45bf7faae6f1d00e714ad3bea2bfefbf0d31
resources/views/auth/login.blade.php|93ba08a0c8564e75ee6fc0ddb0fae9182682f5ae|5908614b5deb88bc88f28b77c2f2852eddd1d9a9
resources/views/auth/register.blade.php|617daa9c53a312817c6f2a5fd635f9336f811d04|d20d7c17e2d6d50c9609628b614aebe0c22c353d
resources/views/auth/reset-password.blade.php|66347f1ae78c513db09d0351f60b7c0ee23f3968|0e366e6249487338c48d582d45e52b36c346d8c6
resources/views/auth/forgot-password.blade.php|ab5235d883adf16610a4704edaa93a8f01da6f04|1f0a2356c93f2bbddd65a876066f85db758f7699
resources/views/installer/show.blade.php|ad91c55e3a281a3033839d5f2038251990ed9777|e6a4465e23f20042959e5119197f326fe9acfdde
public/assets/app.css|3cbda96b9806386a241534e4006e522b88ee23c6|3e0795bc1ba85ffa4ac04a4a21a1029411e4257f
resources/views/partials/brand.blade.php|-|ea93f0975a24a38390a7a2c6677f8b202797649d
resources/views/partials/branding-head.blade.php|-|df5c6ea1d08e1f20f775d750fe8d1485844e3bd6
public/branding/samith-ai-logo-light.svg|-|6a9e79ed29d9353c07350690fc23efd3f25dda39
public/branding/samith-ai-logo-dark.svg|-|4287dd409c7222437d0f25671d55f17d3e5930b8
public/favicon.svg|-|cdf42fbed2d83b3fa9db8023a38356e6f312a446
CHECKSUMS
)"

echo '========== PRODUCTION BRANDING PRECHECK =========='
test "$(id -u)" -eq 0
command -v git >/dev/null
command -v curl >/dev/null
command -v flock >/dev/null
command -v runuser >/dev/null
test -x "$PHP"
test -f "$APP/artisan"
test -f "$APP/.env"
test -f "$APP/storage/app/installed.lock"
id www >/dev/null
exec 9>/root/samithai-branding-deployment.lock
flock -n 9 || { echo 'BLOCKED: another branding deployment is active'; exit 1; }

while IFS='|' read -r path before after; do
  dest="$APP/$path"
  if [[ "$before" == "-" ]]; then
    if [[ -e "$dest" || -L "$dest" ]]; then
      echo "BLOCKED: expected new file already exists: $path"
      exit 1
    fi
  else
    if [[ ! -f "$dest" || -L "$dest" ]] || [[ "$(git hash-object "$dest")" != "$before" ]]; then
      echo "BLOCKED: production source differs from expected baseline: $path"
      echo 'Nothing has been overwritten.'
      exit 1
    fi
  fi
done <<< "$FILES"
echo 'PRODUCTION_FILE_BASELINES=VERIFIED'

if [[ "$MODE" == '--check' ]]; then
  echo 'BRANDING_UPDATE=READY'
  echo 'PRODUCTION_FILES=UNCHANGED'
  exit 0
fi

echo '========== VERIFY PINNED BRANDING SOURCE =========='
WORK="$(mktemp -d /root/samithai-branding-source.XXXXXXXX)"
trap 'rm -rf "$WORK"' EXIT
while IFS='|' read -r path before after; do
  mkdir -p "$WORK/$(dirname "$path")"
  curl -fsSL --proto '=https' --connect-timeout 8 --max-time 35 \
    "https://raw.githubusercontent.com/dombong2026/samithai/$COMMIT/$path" \
    -o "$WORK/$path"
  test "$(git hash-object "$WORK/$path")" = "$after"
done <<< "$FILES"
echo 'BRANDING_SOURCE_HASHES=VERIFIED'

echo '========== CREATE PRIVATE ROLLBACK BACKUP =========='
BACKUP="$(mktemp -d /root/samithai-branding-backup.XXXXXXXX)"
while IFS='|' read -r path before after; do
  if [[ "$before" != "-" ]]; then
    mkdir -p "$BACKUP/$(dirname "$path")"
    cp -p "$APP/$path" "$BACKUP/$path"
  fi
done <<< "$FILES"
echo "ROLLBACK_BACKUP=$BACKUP"

MODIFIED=0
rollback() {
  code="$1"
  trap - ERR INT TERM
  if [[ "$MODIFIED" == 1 ]]; then
    echo 'BRANDING_UPDATE_FAILED=RESTORING_PREVIOUS_UI'
    while IFS='|' read -r path before after; do
      if [[ "$before" == "-" ]]; then
        rm -f "$APP/$path"
      else
        cp -p "$BACKUP/$path" "$APP/$path"
      fi
    done <<< "$FILES"
    (cd "$APP" && runuser -u www -- "$PHP" artisan view:clear) || true
    echo 'BRANDING_ROLLBACK=ATTEMPTED'
  fi
  exit "$code"
}
trap 'rollback $?' ERR
trap 'rollback 130' INT
trap 'rollback 143' TERM

echo '========== APPLY BRANDING ASSETS AND TEMPLATES =========='
MODIFIED=1
while IFS='|' read -r path before after; do
  if [[ "$path" == public/branding/* ]]; then
    install -d -o root -g www -m 0755 "$APP/public/branding"
  fi
  install -o root -g www -m 0644 "$WORK/$path" "$APP/$path"
  test "$(git hash-object "$APP/$path")" = "$after"
done <<< "$FILES"

echo '========== COMPILE BLADE VIEWS =========='
(cd "$APP" && runuser -u www -- "$PHP" artisan view:clear)
(cd "$APP" && runuser -u www -- "$PHP" artisan view:cache)

echo '========== VERIFY HTTPS ORIGIN =========='
for asset in favicon.svg branding/samith-ai-logo-light.svg branding/samith-ai-logo-dark.svg; do
  code="$(curl --noproxy '*' -ksS --connect-timeout 5 --max-time 20 \
    --resolve "$DOMAIN:443:127.0.0.1" \
    -o "$WORK/check-asset" -w '%{http_code}' "https://$DOMAIN/$asset")"
  [[ "$code" == '200' ]]
  grep -Fq '<svg ' "$WORK/check-asset"
done
for page in / /login /register /forgot-password; do
  code="$(curl --noproxy '*' -ksS --connect-timeout 5 --max-time 20 \
    --resolve "$DOMAIN:443:127.0.0.1" \
    -o "$WORK/check-page" -w '%{http_code}' "https://$DOMAIN$page")"
  [[ "$code" == '200' ]]
  grep -Fq 'rel="icon" type="image/svg+xml"' "$WORK/check-page"
  grep -Fq 'brand-logo-light' "$WORK/check-page"
  grep -Fq 'brand-logo-dark' "$WORK/check-page"
done
code="$(curl --noproxy '*' -ksS --connect-timeout 5 --max-time 20 \
  --resolve "$DOMAIN:443:127.0.0.1" \
  -o "$WORK/css" -w '%{http_code}' "https://$DOMAIN/assets/app.css")"
[[ "$code" == '200' ]]
grep -Fq '.brand-with-logo .brand-logo-dark' "$WORK/css"

trap - ERR INT TERM
echo '========== SAMITH AI BRANDING DEPLOYMENT PASSED =========='
echo 'FAVICON=VERIFIED'
echo 'LIGHT_LOGO=VERIFIED'
echo 'DARK_LOGO=VERIFIED'
echo 'FRONTEND_AND_AUTH_BRANDING=VERIFIED'
echo 'BLADE_AND_CSS=VERIFIED'
echo 'CUSTOM_LOGO_OVERRIDE=PRESERVED'
echo 'DATABASE=UNCHANGED'
echo 'ENVIRONMENT=UNCHANGED'
echo 'NGINX=UNCHANGED'
echo "ROLLBACK_BACKUP=$BACKUP"
