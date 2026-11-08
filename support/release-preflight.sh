#!/usr/bin/env bash
# Read-only release diagnostics. No migrations, backups, deletion or deployment.
set -Eeo pipefail
umask 077
ROOT="."
if [[ $# -gt 0 ]]; then ROOT="$1"; fi
cd "$ROOT"
ROOT="$(pwd -P)"
FAILED=0
REVIEW=0
ok(){ printf 'PASS   %s\n' "$1"; }
no(){ printf 'FAIL   %s\n' "$1"; FAILED=$((FAILED+1)); }
unknown(){ printf 'REVIEW %s\n' "$1"; REVIEW=$((REVIEW+1)); }
for file in artisan public/index.php vendor/autoload.php .env public/assets/app.css public/assets/app.js; do
  if [[ -f "$file" ]]; then ok "$file"; else no "Missing $file"; fi
done
for dir in storage/framework bootstrap/cache; do
  if [[ -d "$dir" ]]; then ok "$dir"; else no "Missing $dir"; fi
done
if [[ -f storage/app/installed.lock ]]; then ok "Installer locked"; else unknown "No installation lock"; fi
if [[ -f composer.lock && -f package-lock.json ]]; then ok "Composer and npm dependency lockfiles"; else unknown "Dependency lockfiles incomplete; release not reproducible"; fi
if [[ -e public/.env || -e public/vendor || -e public/storage/app ]]; then no "Exposed sensitive filesystem path"; else ok "No sensitive files at common public paths"; fi
if [[ -f .env ]]; then
  if grep -qE '^APP_DEBUG=(true|"true")$' .env; then no "APP_DEBUG enabled"; else ok "Debug disabled"; fi
  if grep -qE '^APP_KEY=base64:' .env; then ok "APP_KEY present"; else no "APP_KEY missing"; fi
fi
if command -v php >/dev/null; then
  if php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);'; then ok "PHP >= 8.3"; else no "PHP version"; fi
  if php -r 'exit(extension_loaded("pdo_mysql") && extension_loaded("openssl") && extension_loaded("mbstring") && extension_loaded("dom") && extension_loaded("fileinfo") ? 0 : 1);'; then ok "PHP extensions"; else no "Required PHP extensions"; fi
else unknown "PHP CLI unavailable: inspect hosting control panel"; fi
if [[ -n "$SAMITH_DOCUMENT_ROOT" ]]; then
  DOCUMENT_ROOT="$(cd "$SAMITH_DOCUMENT_ROOT" && pwd -P)"
  if [[ "$DOCUMENT_ROOT" == "$ROOT/public" ]]; then ok "Document root points to public/"; else no "Incorrect document root"; fi
else unknown "Set SAMITH_DOCUMENT_ROOT to check webroot"; fi
if [[ -n "$SAMITH_TEST_URL" ]]; then
  if command -v curl >/dev/null; then
    for path in /.env /vendor/autoload.php /storage/app/installed.lock /.git/config; do
      STATUS="$(curl --silent --show-error --max-time 15 --output /dev/null --write-out '%{http_code}' "$SAMITH_TEST_URL$path" || true)"
      if [[ "$STATUS" == 404 || "$STATUS" == 403 ]]; then ok "Sensitive URL $path ($STATUS)"; else no "Sensitive URL $path ($STATUS)"; fi
    done
  else unknown "No curl available"; fi
else unknown "Set SAMITH_TEST_URL to check HTTP exposure"; fi
printf 'PREFLIGHT finished: failed=%d review=%d\n' "$FAILED" "$REVIEW"
(( FAILED == 0 ))
