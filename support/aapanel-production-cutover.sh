#!/usr/bin/env bash
# aaPanel production cutover: narrowly changes the existing Nginx root to Laravel public/.
# --check reads only; --apply creates a private backup and rolls back on failed origin checks.
set -Eeuo pipefail
umask 077
if [ "$#" -ne 1 ] || { [ "$1" != '--check' ] && [ "$1" != '--apply' ]; }; then
    echo 'Usage: bash support/aapanel-production-cutover.sh --check|--apply' >&2
    exit 2
fi
MODE="$1"
DOMAIN='samithai.com'
SHA='3c9bf3fda1d0df308b94a60b006f36c437e7bf49'
APP="/www/wwwroot/samithai-releases/$SHA"
OLD_ROOT="/www/wwwroot/$DOMAIN"
VHOST="/www/server/panel/vhost/nginx/$DOMAIN.conf"
REWRITE="/www/server/panel/vhost/rewrite/$DOMAIN.conf"
NGINX='/www/server/nginx/sbin/nginx'
PHP='/www/server/php/85/bin/php'

echo '========== RELEASE AND WEBROOT PREFLIGHT =========='
[ "$(id -u)" -eq 0 ]
test -x "$NGINX" && test -x "$PHP"
test -f "$VHOST" && test -f "$REWRITE"
test -d "$OLD_ROOT" && test -d "$APP/public"
test -f "$APP/public/index.php"
test -f "$APP/public/assets/app.css"
test -f "$APP/vendor/autoload.php"
test -f "$APP/.env"
test ! -e "$APP/storage/app/installed.lock"
test -z "$(find "$OLD_ROOT" -mindepth 1 -maxdepth 1 -print -quit)"
grep -Eq '^[[:space:]]*root[[:space:]]+/www/wwwroot/samithai\.com[[:space:]]*;' "$VHOST"
grep -Fq 'include enable-php-85.conf;' "$VHOST"
grep -Fq 'include /www/server/panel/vhost/rewrite/samithai.com.conf;' "$VHOST"
grep -Fq 'try_files $uri $uri/ /index.php?$query_string;' "$REWRITE"
python3 - "$APP/.env" <<'PY'
import sys
from pathlib import Path
v = {}
for line in Path(sys.argv[1]).read_text().splitlines():
    if '=' in line and not line.lstrip().startswith('#'):
        key, value = line.split('=', 1)
        v[key.strip()] = value.strip().strip('"')
expected = {
    'APP_ENV': 'production',
    'APP_DEBUG': 'false',
    'APP_URL': 'https://samithai.com',
    'SESSION_DRIVER': 'file',
    'CACHE_STORE': 'file',
    'QUEUE_CONNECTION': 'sync',
    'SAMITH_PAYMENTS_ENABLED': 'false',
    'SAMITH_EMAIL_DELIVERY_ENABLED': 'false',
    'SAMITH_SMTP_PROBE_ENABLED': 'false',
}
for key, value in expected.items():
    if v.get(key) != value:
        raise SystemExit('BLOCKED: unsafe value for ' + key)
if not v.get('APP_KEY', '').startswith('base64:') or len(v.get('INSTALL_TOKEN', '')) != 48:
    raise SystemExit('BLOCKED: missing application key or installer token')
print('PRODUCTION_ENVIRONMENT=VERIFIED')
PY
runuser -u www -- test -r "$APP/public/index.php"
runuser -u www -- test -r "$APP/.env"
"$NGINX" -t >/dev/null
echo 'NGINX_PREFLIGHT=PASSED'
if [ "$MODE" = '--check' ]; then
    echo 'CUTOVER=NOT_APPLIED'
    echo 'DATABASE=UNCHANGED'
    exit 0
fi

echo '========== SAVE EXISTING NGINX CONFIG =========='
BACKUP="$(mktemp -d /root/samithai-nginx-cutover.XXXXXXXX)"
cp -p "$VHOST" "$BACKUP/samithai.com.conf"
cp -p "$REWRITE" "$BACKUP/samithai.com.rewrite.conf"
echo "BACKUP_DIRECTORY=$BACKUP"
CUTOVER=0
rollback() {
    rc="$1"
    trap - ERR INT TERM
    if [ "$CUTOVER" -eq 1 ]; then
        echo 'CUTOVER_FAILED=RESTORING_OLD_CONFIG'
        cp -p "$BACKUP/samithai.com.conf" "$VHOST"
        if "$NGINX" -t >/dev/null 2>&1 && "$NGINX" -s reload >/dev/null 2>&1; then
            echo 'NGINX_ROLLBACK=SUCCESS'
        else
            echo 'NGINX_ROLLBACK=OPERATOR_REVIEW_REQUIRED'
        fi
    fi
    exit "$rc"
}
trap 'rollback $?' ERR
trap 'rollback 130' INT
trap 'rollback 143' TERM

echo '========== SWITCH NGINX DOCUMENT ROOT =========='
TMP="$(mktemp "$VHOST.cutover.XXXXXXXX")"
cp -p "$VHOST" "$TMP"
python3 - "$TMP" "$APP/public" <<'PY'
import pathlib, re, sys
p = pathlib.Path(sys.argv[1])
original = p.read_text()
updated, count = re.subn(
    r'(?m)^([ \t]*root[ \t]+)/www/wwwroot/samithai\.com([ \t]*;)',
    lambda m: m.group(1) + sys.argv[2] + m.group(2),
    original,
)
if count != 1:
    raise SystemExit('BLOCKED: expected exactly one Nginx root directive')
p.write_text(updated)
PY
mv -f "$TMP" "$VHOST"
CUTOVER=1
"$NGINX" -t
"$NGINX" -s reload
sleep 2

echo '========== CHECK LOCAL HTTPS ORIGIN =========='
BODY="$(mktemp /root/samithai-installer-smoke.XXXXXXXX)"
trap 'rm -f "$BODY"' EXIT
CODE="$(curl --noproxy '*' -k -sS --connect-timeout 5 --max-time 20 --resolve "$DOMAIN:443:127.0.0.1" -o "$BODY" -w '%{http_code}' "https://$DOMAIN/installer")"
if [ "$CODE" != 200 ] || ! grep -Fq 'Install Samith AI' "$BODY"; then
    echo "INSTALLER_ORIGIN_CHECK=FAILED_HTTP_$CODE"
    false
fi
CSS="$(curl --noproxy '*' -k -sS --connect-timeout 5 --max-time 15 --resolve "$DOMAIN:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$DOMAIN/assets/app.css")"
if [ "$CSS" != 200 ]; then
    echo "CSS_ORIGIN_CHECK=FAILED_HTTP_$CSS"
    false
fi
PUBLIC="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 15 -o /dev/null -w '%{http_code}' "https://$DOMAIN/installer" 2>/dev/null || true)"
trap - ERR INT TERM
rm -f "$BODY"
trap - EXIT

echo '========== PRODUCTION CUTOVER RESULT =========='
echo 'NGINX_ROOT=SAMITH_LARAVEL_PUBLIC'
echo 'NGINX_CONFIG_TEST=PASSED'
echo 'INSTALLER_ORIGIN_HTTP=200'
echo 'STYLESHEET_ORIGIN_HTTP=200'
echo "PUBLIC_HTTPS_HTTP=$PUBLIC"
echo 'DATABASE=NOT_INITIALIZED'
echo 'PAYMENTS=DISABLED'
echo 'SMTP=DISABLED'
echo 'INSTALLER_URL=https://samithai.com/installer'
echo 'Local origin check bypassed cert validation; public HTTPS check used normal verification.'
