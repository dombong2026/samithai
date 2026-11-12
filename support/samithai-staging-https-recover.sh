#!/usr/bin/env bash
# Targeted recovery for the existing private 2026-11-12 staging TLS failure.
# Does not reload the server's Nginx/OpenResty or access production data.
set -Eeuo pipefail
set +x
umask 077

[[ $# == 2 && "$1" =~ ^(--recover|--status|--stop)$ ]] || {
    echo "Usage: bash script.sh --recover|--status|--stop /absolute/verified/samithai-staging-tls-proxy.py" >&2
    exit 2
}
MODE="$1"
SOURCE="$2"
LAB="/var/lib/samithai-editorial-staging-20261112"
APP="$LAB/releases/candidate"
PROXY="$LAB/tls-proxy.py"
PIDFILE="$LAB/tls-proxy.pid"
PHP="/www/server/php/85/bin/php"
HEAD="54c92ba0817b31673e81972c1d7beebe2bf9f17e"

fail() { echo "BLOCKED=$*" >&2; echo "STAGING_LAB=PRESERVED"; exit 3; }

[[ "$(id -u)" == 0 ]] || fail "Root required for private staging TLS key"
for exe in git curl python3 flock ps setsid; do
    command -v "$exe" >/dev/null || fail "Missing $exe"
done
[[ -d "$LAB" && ! -L "$LAB" && -d "$APP" && ! -L "$APP" ]] ||
    fail "Unrecognized staging directory"
[[ -L "$LAB/current" && "$(realpath -e "$LAB/current")" == "$APP" ]] ||
    fail "Staging pointer is not the candidate release"
[[ -f "$APP/artisan" && -f "$APP/.env" && -f "$LAB/shared/staging.sqlite" ]] ||
    fail "Staging source missing"
[[ "$(git -C "$APP" rev-parse HEAD)" == "$HEAD" ]] ||
    fail "Existing staging checkout SHA changed"
[[ -f "$LAB/tls/cert.pem" && -f "$LAB/tls/key.pem" ]] ||
    fail "Existing staging TLS certificate is missing"
[[ -f "$LAB/shared/storage/app/staging-credentials.json" ]] ||
    fail "Isolated account credentials missing"

"$PHP" -r '
require $argv[3]."/vendor/autoload.php";
$e=Dotenv\Dotenv::parse(file_get_contents($argv[1]));
if (($e["APP_ENV"]??"")!=="staging"
    || ($e["DB_CONNECTION"]??"")!=="sqlite"
    || ($e["DB_DATABASE"]??"")!==$argv[2]
    || ($e["MAIL_MAILER"]??"")!=="array") exit(4);
echo "ISOLATED_STAGING_ENVIRONMENT=PASS\n";
' "$LAB/shared/.env" "$LAB/shared/staging.sqlite" "$APP" ||
    fail "Staging environment guard failed"

status() {
    local code
    code="$(curl --noproxy '*' -ksS --max-time 4 -o /dev/null \
        -w '%{http_code}' 'https://127.0.0.1:8843/login' 2>/dev/null || true)"
    if [[ "$code" == 200 ]]; then
        echo "PRIVATE_HTTPS_LOGIN=200_PASS"
        echo "PRIVATE_PREVIEW_URL=https://127.0.0.1:8843/login"
        echo "TLS_CERTIFICATE=STAGING_SELF_SIGNED"
        return 0
    fi
    echo "PRIVATE_HTTPS_LOGIN=NOT_READY_$code"
    return 1
}

if [[ "$MODE" == --status ]]; then
    status || exit 3
    echo "PRODUCTION_MYSQL_NGINX_CRON=UNCHANGED"
    exit 0
fi

exec 9>/run/lock/samithai-editorial-staging-tls-recover.lock
flock -n 9 || fail "Concurrent staging TLS change"

if [[ "$MODE" == --stop ]]; then
    if [[ ! -e "$PIDFILE" && ! -L "$PIDFILE" ]]; then
        echo "PRIVATE_STAGING_TLS_PROXY=NOT_RUNNING_BY_THIS_SCRIPT"
        exit 0
    fi
    [[ -f "$PIDFILE" && ! -L "$PIDFILE" ]] || fail "Unsafe PID file"
    pid="$(cat "$PIDFILE")"
    [[ "$pid" =~ ^[0-9]+$ ]] || fail "Invalid PID"
    if kill -0 "$pid" 2>/dev/null; then
        args="$(ps -p "$pid" -o args= 2>/dev/null || true)"
        [[ "$args" == *"$PROXY"* ]] || fail "Refuse to terminate unrelated process"
        kill -TERM "$pid" || fail "TLS stop failed"
    fi
    rm -f -- "$PIDFILE"
    echo "STAGING_TLS_STOP=REQUESTED"
    echo "STAGING_HTTP_DB_AND_STORAGE=PRESERVED"
    echo "PRODUCTION=UNCHANGED"
    exit 0
fi

echo "========== RECOVER PRIVATE TLS WITHOUT NGINX =========="
[[ "$SOURCE" == /* && -f "$SOURCE" && ! -L "$SOURCE" ]] ||
    fail "Verified Python TLS source required"
[[ ! -e "$PIDFILE" && ! -L "$PIDFILE" ]] || fail "TLS PID already exists"
[[ ! -e "$PROXY" && ! -L "$PROXY" ]] || fail "TLS proxy already installed"
# Previous aaPanel OpenResty attempt may leave a malformed/empty PID file.
# Archive it only if NO process in this staging prefix and NO HTTPS listener exists.
legacy_pid="$LAB/proxy/nginx.pid"
if [[ -e "$legacy_pid" || -L "$legacy_pid" ]]; then
    [[ -f "$legacy_pid" && ! -L "$legacy_pid" ]] ||
        fail "Unexpected isolated OpenResty PID file type"
    [[ "$(stat -c '%u' "$legacy_pid")" == 0 ]] ||
        fail "Unexpected isolated OpenResty PID file ownership"
    [[ "$(stat -c '%s' "$legacy_pid")" -le 128 ]] ||
        fail "Unexpected isolated OpenResty PID file length"
    command -v ss >/dev/null || fail "ss is required to verify no TLS listener"
    [[ -z "$(ss -H -ltn '( sport = :8843 )')" ]] ||
        fail "HTTPS port 8843 already has a listener"
    if ps -eo args= | grep -F "$LAB/proxy/" | grep -Eq '[n]ginx|[o]penresty'; then
        fail "Isolated OpenResty process still exists"
    fi
    oldpid="$(tr -d '\r\n' < "$legacy_pid")"
    if [[ "$oldpid" =~ ^[0-9]+$ ]] && kill -0 "$oldpid" 2>/dev/null; then
        fail "OpenResty PID refers to a running process"
    fi
    archived="$legacy_pid.stale-$(date -u +%Y%m%dT%H%M%SZ)-$"
    [[ ! -e "$archived" && ! -L "$archived" ]] ||
        fail "Stale PID archive already exists"
    mv -- "$legacy_pid" "$archived"
    [[ ! -e "$legacy_pid" && -f "$archived" ]] ||
        fail "Stale PID archive verification failed"
    chmod 600 "$archived"
    echo "STAGING_OPENRESTY_PID=SAFELY_ARCHIVED"
    echo "STAGING_OPENRESTY_PID_ARCHIVE=$archived"
fi

[[ -f "$LAB/server.pid" && ! -L "$LAB/server.pid" ]] ||
    fail "Existing private PHP server PID missing"
pid="$(cat "$LAB/server.pid")"
[[ "$pid" =~ ^[0-9]+$ ]] || fail "Invalid private PHP PID"
kill -0 "$pid" 2>/dev/null || fail "Existing private PHP server exited"
args="$(ps -p "$pid" -o args= 2>/dev/null || true)"
[[ "$args" == *"127.0.0.1:8331"* && "$args" == *"$LAB/router.php"* ]] ||
    fail "Private PHP server process does not match the staging launcher"
code="$(curl --noproxy '*' -sS --max-time 5 -o /dev/null \
    -w '%{http_code}' 'http://127.0.0.1:8331/login' 2>/dev/null || true)"
[[ "$code" == 200 ]] || fail "Existing private HTTP backend returned $code"
echo "PRIVATE_HTTP_BACKEND=200_PASS"

python3 - <<'PY'
import socket
with socket.socket() as s:
    s.bind(("127.0.0.1", 8843))
print("HTTPS_LOOPBACK_PORT=AVAILABLE")
PY

python3 - <<'PY' "$SOURCE"
from pathlib import Path
import ast,sys
ast.parse(Path(sys.argv[1]).read_text(encoding="utf-8"))
print("VERIFIED_TLS_PROXY_SYNTAX=PASS")
PY
install -o root -g root -m 600 "$SOURCE" "$PROXY"
cmp -s -- "$SOURCE" "$PROXY" || fail "TLS proxy copy integrity failure"

setsid python3 "$PROXY" > "$LAB/tls-proxy.log" 2>&1 </dev/null &
newpid="$!"
printf '%s\n' "$newpid" > "$PIDFILE"
chmod 600 "$PIDFILE"

ready=0
for _ in $(seq 1 40); do
    if status >/dev/null 2>&1; then ready=1; break; fi
    kill -0 "$newpid" 2>/dev/null || break
    sleep 0.2
done
if [[ "$ready" != 1 ]]; then
    echo "PRIVATE_PROXY_LOG=$LAB/tls-proxy.log"
    fail "Private HTTPS proxy is not responding"
fi
status
echo "STAGING_TLS_RECOVERY=PASS"
echo "SIX_ARTICLES_AND_SOCIAL_IMAGE_LINKS=UNCHANGED"
echo "PRODUCTION_MYSQL_NGINX_CRON=UNCHANGED"
echo "MAC_TUNNEL=ssh -N -L 8843:127.0.0.1:8843 root@YOUR_SERVER"
