#!/usr/bin/env bash
# Operator-initiated READ ONLY staging-host preflight; no migrations, sends, writes or deployment.
set -Eeo pipefail
umask 077
ROOT="$1"
if [[ -z "$ROOT" ]]; then ROOT='.'; fi
ROOT="$(cd "$ROOT" && pwd -P)"
URL="$SAMITH_TEST_URL"
DOCUMENT_ROOT="$SAMITH_DOCUMENT_ROOT"
FAILED=0
REVIEW=0
PASS=0
pass() { printf 'PASS   %s\n' "$1"; PASS=$((PASS + 1)); }
fail() { printf 'FAIL   %s\n' "$1"; FAILED=$((FAILED + 1)); }
review() { printf 'REVIEW %s\n' "$1"; REVIEW=$((REVIEW + 1)); }

printf 'SAMITH_AI_EXTERNAL_HOST_PROBE=READ_ONLY\n'
printf 'HOST_CLASS=%s\n' "$SAMITH_HOST_CLASS"
for file in artisan public/index.php vendor/autoload.php composer.lock package-lock.json; do
    if [[ -f "$ROOT/$file" ]]; then pass "Required file $file"; else fail "Required file missing: $file"; fi
done
if [[ -d "$ROOT/storage/app/private" && -d "$ROOT/bootstrap/cache" ]]; then
    pass 'Private storage and bootstrap cache present'
else
    fail 'Private storage or bootstrap cache missing'
fi
if [[ -e "$ROOT/public/.env" || -e "$ROOT/public/.git" || -e "$ROOT/public/vendor" || -e "$ROOT/public/storage/app" ]]; then
    fail 'Forbidden private path located within public/'
else
    pass 'No common private paths inside public/'
fi

if [[ -f "$ROOT/.env" ]]; then
    mode="$(stat -c '%a' "$ROOT/.env" 2>/dev/null || stat -f '%Lp' "$ROOT/.env" 2>/dev/null || echo unknown)"
    if [[ "$mode" == unknown ]]; then
        review '.env mode could not be checked'
    elif (( (8#$mode & 0007) == 0 )); then
        pass '.env is not world-readable'
    else
        fail '.env permissions allow access by other users'
    fi
else
    review '.env absent; installation not yet accepted'
fi
if [[ -f "$ROOT/storage/app/installed.lock" ]]; then pass 'Installer locked'; else review 'No installation lock'; fi

if [[ -n "$DOCUMENT_ROOT" ]]; then
    if [[ -d "$DOCUMENT_ROOT" ]] && [[ "$(cd "$DOCUMENT_ROOT" && pwd -P)" == "$ROOT/public" ]]; then
        pass 'Document root is Laravel public/'
    else
        fail 'Document root does not match Laravel public/'
    fi
else
    review 'Document root not supplied; set SAMITH_DOCUMENT_ROOT'
fi

if command -v php >/dev/null 2>&1; then
    if php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);'; then pass 'PHP CLI >= 8.3'; else fail 'PHP CLI below 8.3'; fi
    if php -r '$e=["pdo_mysql","openssl","mbstring","xml","dom","fileinfo"];foreach($e as $n){if(!extension_loaded($n))exit(1);}'; then
        pass 'Required PHP extensions available'
    else
        fail 'Required PHP extensions missing'
    fi
else
    review 'No PHP CLI; inspect hosting PHP configuration'
fi

if [[ -n "$URL" ]]; then
    if [[ ! "$URL" =~ ^https://[A-Za-z0-9.-]+(:[0-9]+)?/?$ ]]; then
        fail 'SAMITH_TEST_URL must be a bare HTTPS URL, without path or credentials'
    elif ! command -v curl >/dev/null 2>&1; then
        review 'curl missing; HTTPS path checks not performed'
    else
        URL="$(printf '%s' "$URL" | sed 's#/$##')"
        for path in /.env /.git/config /vendor/autoload.php /storage/app/installed.lock /bootstrap/cache/config.php; do
            code="$(curl -sS --proto '=https' --connect-timeout 5 --max-time 12 --max-redirs 0 \
                --output /dev/null --write-out '%{http_code}' "$URL$path" 2>/dev/null || true)"
            if [[ "$code" == 403 || "$code" == 404 || "$code" == 410 ]]; then
                pass "Sensitive path blocked: $path"
            else
                fail "Sensitive path unverified/accessible: $path (HTTP $code)"
            fi
        done
        code="$(curl -sS --proto '=https' --connect-timeout 5 --max-time 12 --max-redirs 0 \
            --output /dev/null --write-out '%{http_code}' "$URL/up" 2>/dev/null || true)"
        if [[ "$code" == 200 ]]; then pass 'HTTPS health endpoint responded'; else review "HTTPS health endpoint HTTP $code"; fi
    fi
else
    review 'HTTPS checks not run; set SAMITH_TEST_URL'
fi
printf 'EXTERNAL_HOST_PREFLIGHT pass=%d review=%d fail=%d\n' "$PASS" "$REVIEW" "$FAILED"
printf 'This does not prove independent host, backup, payment or security acceptance.\n'
if (( FAILED > 0 )); then exit 1; fi
if (( REVIEW > 0 )); then exit 2; fi
exit 0
