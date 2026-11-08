#!/usr/bin/env bash
# READ-ONLY discovery of the existing samithai.com aaPanel production host.
# This does not install packages, create DB users, change Nginx or modify files.
set -Eeuo pipefail
umask 077

DOMAIN="samithai.com"
VHOST="/www/server/panel/vhost/nginx/$DOMAIN.conf"
ROOT="/www/wwwroot/$DOMAIN"
STATUS=0

pass() { printf 'PASS   %s\n' "$1"; }
review() { printf 'REVIEW %s\n' "$1"; STATUS=2; }
block() { printf 'BLOCK  %s\n' "$1"; STATUS=1; }

printf 'SAMITH_AI_PRODUCTION_DISCOVERY=READ_ONLY\n'
printf 'DOMAIN=%s\n' "$DOMAIN"
printf 'TIME_UTC=%s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')"

if [[ -f "$VHOST" ]]; then
    pass "aaPanel Nginx vhost exists"
    if grep -Eq 'server_name[[:space:]]+([^;[:space:]]+[[:space:]]+)*samithai\.com([[:space:];]|$)' "$VHOST"; then
        pass "samithai.com appears in the configured server names"
    else
        review "Domain not confirmed in vhost server_name; inspect aaPanel Website"
    fi
    if grep -Eq '^[[:space:]]*root[[:space:]]+[^;]+;' "$VHOST"; then
        review "Nginx root is configured; verify its exact resolved directory in aaPanel before switching it"
    else
        block "Nginx document root not found"
    fi
else
    review "No aaPanel Nginx vhost at expected location; inspect site mapping"
fi

if [[ -d "$ROOT" ]]; then
    pass "Conventional aaPanel site directory exists"
    # Print counts only: never list private file names or read their contents.
    first_level="$(find "$ROOT" -mindepth 1 -maxdepth 1 -print -quit 2>/dev/null || true)"
    if [[ -z "$first_level" ]]; then
        pass "Conventional site directory has no first-level entries"
    else
        review "Site directory contains files or folders; confirm ownership and preserve them"
    fi
    if [[ -f "$ROOT/.env" || -f "$ROOT/artisan" || -f "$ROOT/storage/app/installed.lock" ]]; then
        block "Existing Laravel/application markers found; do not overwrite or reinstall"
    fi
    if [[ -L "$ROOT" ]]; then
        review "Site directory is a symlink; identify its target before any changes"
    fi
else
    review "Conventional site directory does not exist; check Nginx root"
fi

if command -v nginx >/dev/null 2>&1; then
    if nginx -t >/dev/null 2>&1; then pass "Nginx syntax valid"; else block "Nginx configuration validation failed"; fi
elif [[ -x /www/server/nginx/sbin/nginx ]]; then
    if /www/server/nginx/sbin/nginx -t >/dev/null 2>&1; then pass "aaPanel Nginx syntax valid"; else block "aaPanel Nginx validation failed"; fi
else
    review "Nginx binary not found in conventional locations"
fi

if [[ -x /www/server/php/85/bin/php ]]; then
    if /www/server/php/85/bin/php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);'; then
        pass "aaPanel PHP 8.5 CLI meets Laravel 13 minimum"
    else
        block "aaPanel PHP 8.5 CLI does not meet minimum"
    fi
    if /www/server/php/85/bin/php -r '$e=["pdo_mysql","openssl","mbstring","dom","fileinfo","xml"];foreach($e as $n){if(!extension_loaded($n))exit(1);}'; then
        pass "aaPanel PHP 8.5 CLI has required extensions"
    else
        block "aaPanel PHP 8.5 CLI lacks a required extension"
    fi
else
    review "aaPanel PHP 8.5 CLI unavailable; check actual FPM version in Website settings"
fi

if command -v getent >/dev/null 2>&1; then
    ip="$(getent ahostsv4 "$DOMAIN" 2>/dev/null | awk 'NR==1{print $1}' || true)"
    if [[ -n "$ip" ]]; then
        pass "Domain resolves via server DNS (actual answer should be checked by operator)"
    else
        review "Server DNS did not resolve the domain"
    fi
else
    review "No getent command; verify domain DNS independently"
fi

if command -v curl >/dev/null 2>&1; then
    code="$(curl --silent --show-error --proto '=https' --max-time 10 --connect-timeout 5 \
        --output /dev/null --write-out '%{http_code}' "https://$DOMAIN/" 2>/dev/null || true)"
    if [[ "$code" =~ ^[1-5][0-9][0-9]$ ]]; then
        review "HTTPS endpoint responded HTTP $code; verify certificate, routing and any existing data"
    else
        review "HTTPS response unavailable; verify TLS and DNS"
    fi
else
    review "curl unavailable; verify TLS/HTTP manually"
fi

printf 'NEXT_STEP=Compare actual vhost root, release ZIP SHA256, web data and MySQL schema before installation.\n'
printf 'LIVE_PRODUCTION_CHANGES=NONE\n'
exit "$STATUS"
