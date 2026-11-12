#!/usr/bin/env bash
# Loopback HTTPS for the isolated Samith AI staging releases only.
# No public vhost, production cron, global Nginx reload, or production database.
set -Eeuo pipefail
set +x
umask 077

[[ $# == 1 ]] || { echo 'Usage: script --start|--stop|--status|--candidate|--rollback' >&2; exit 2; }
MODE="$1"
[[ "$MODE" =~ ^(--start|--stop|--status|--candidate|--rollback)$ ]] ||
    { echo 'Unknown staging action' >&2; exit 2; }

LAB="/var/lib/samithai-editorial-staging-20261112"
PROXY="$LAB/proxy"
CURRENT="$LAB/current"
PHP="/www/server/php/85/bin/php"
NGINX="/www/server/nginx/sbin/nginx"
PORT_HTTP=8331
PORT_HTTPS=8843
fail() { echo "BLOCKED=$*" >&2; exit 3; }

[[ "$(id -u)" == 0 && -x "$PHP" ]] || fail "Root/PHP unavailable"
[[ -d "$LAB" && ! -L "$LAB" && -L "$CURRENT" &&
   -d "$LAB/releases/candidate" && -d "$LAB/releases/rollback" &&
   -f "$LAB/shared/staging.sqlite" && -f "$LAB/shared/.env" ]] ||
    fail "The isolated staging release has not been prepared"

[[ "$(realpath -e "$CURRENT")" == "$LAB/releases/candidate" ||
   "$(realpath -e "$CURRENT")" == "$LAB/releases/rollback" ]] ||
    fail "Unexpected staging active release target"

if [[ "$MODE" == --status ]]; then
    echo "STAGING_RELEASE=$(basename "$(realpath -e "$CURRENT")")"
    echo "STAGING_DATA=ISOLATED_SQLITE"
    if curl --noproxy '*' -ks --max-time 3 \
      "https://127.0.0.1:$PORT_HTTPS/login" -o /dev/null 2>/dev/null; then
        echo "STAGING_HTTPS=RESPONDING"
    else
        echo "STAGING_HTTPS=NOT_RESPONDING"
    fi
    echo "PRODUCTION=UNCHANGED"
    exit 0
fi

exec 9>/run/lock/samithai-editorial-staging-https.lock
flock -n 9 || fail "Concurrent staging action"

if [[ "$MODE" == --candidate || "$MODE" == --rollback ]]; then
    [[ ! -e "$LAB/server.pid" ]] ||
        fail "Stop staging HTTP before changing release pointer"
    target="$LAB/releases/candidate"
    [[ "$MODE" == --rollback ]] && target="$LAB/releases/rollback"
    next="$LAB/current.next.$$"
    [[ ! -e "$next" && ! -L "$next" ]] || fail "Unexpected pending staging pointer"
    ln -s "$target" "$next"
    mv -Tf "$next" "$CURRENT"
    [[ "$(realpath -e "$CURRENT")" == "$target" ]] || fail "Pointer move failed"
    echo "STAGING_POINTER=$(basename "$target")"
    echo "PERSISTENT_DATA=UNCHANGED"
    echo "PRODUCTION_POINTER=UNCHANGED"
    exit 0
fi

if [[ "$MODE" == --stop ]]; then
    if [[ -f "$PROXY/nginx.conf" && -f "$PROXY/nginx.pid" ]]; then
        "$NGINX" -p "$PROXY/" -c "$PROXY/nginx.conf" -s quit ||
            fail "Private Nginx quit failed"
    fi
    if [[ -f "$LAB/server.pid" && ! -L "$LAB/server.pid" ]]; then
        pid="$(cat "$LAB/server.pid")"
        [[ "$pid" =~ ^[0-9]+$ ]] || fail "Invalid stage server pid"
        if kill -0 "$pid" 2>/dev/null; then
            command="$(ps -p "$pid" -o args= 2>/dev/null || true)"
            pgid="$(ps -p "$pid" -o pgid= 2>/dev/null | tr -d ' ' || true)"
            [[ "$command" == *"127.0.0.1:$PORT_HTTP"* &&
               "$command" == *"$LAB/router.php"* &&
               "$pgid" == "$pid" ]] ||
                fail "Server identity changed; don't kill an unknown process"
            kill -TERM -- "-$pid"
        fi
        rm -f -- "$LAB/server.pid"
    fi
    echo "PRIVATE_STAGING_SERVERS=STOP_REQUESTED"
    echo "SHARED_STAGING_DATA=PRESERVED"
    echo "PRODUCTION=UNCHANGED"
    exit 0
fi

[[ "$MODE" == --start ]] || fail "Unsupported mode"
[[ -x "$NGINX" ]] || fail "aaPanel Nginx unavailable"
for name in openssl curl setsid flock python3 runuser; do
    command -v "$name" >/dev/null || fail "Missing $name"
done
[[ ! -e "$LAB/server.pid" && ! -e "$PROXY/nginx.pid" ]] ||
    fail "Staging servers may already be running"

python3 - "$PORT_HTTP" "$PORT_HTTPS" <<'PY'
import socket,sys
for port in map(int,sys.argv[1:]):
    with socket.socket() as sock: sock.bind(('127.0.0.1',port))
print('LOOPBACK_HTTP_AND_HTTPS_PORTS=AVAILABLE')
PY

install -d -m 750 -o root -g www "$PROXY"
install -d -m 700 "$LAB/tls"
if [[ ! -f "$LAB/tls/key.pem" || ! -f "$LAB/tls/cert.pem" ]]; then
    openssl req -x509 -newkey rsa:2048 -sha256 -nodes -days 7 \
        -subj '/CN=127.0.0.1' \
        -addext 'subjectAltName=IP:127.0.0.1' \
        -keyout "$LAB/tls/key.pem" -out "$LAB/tls/cert.pem" \
        > "$LAB/tls-setup.log" 2>&1 ||
        fail "Staging self-signed certificate creation failed"
    chmod 600 "$LAB/tls/key.pem"
fi

cat > "$LAB/router.php" <<'PHP'
<?php
// Dedicated localhost HTTP router behind the isolated TLS reverse proxy.
$public = realpath(__DIR__.'/current/public');
if (!$public || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    http_response_code(403); exit;
}
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (is_string($path)) {
    $asset = realpath($public.$path);
    if ($asset && str_starts_with($asset,$public.DIRECTORY_SEPARATOR)
        && is_file($asset)) return false;
}
$_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_SCHEME'] = 'https';
$_SERVER['SERVER_PORT'] = '8843';
require $public.'/index.php';
PHP
chown root:www "$LAB/router.php"
chmod 640 "$LAB/router.php"

(
    cd "$CURRENT"
    exec setsid runuser -u www -- env -i \
        PATH=/www/server/php/85/bin:/usr/local/bin:/usr/bin:/bin \
        HOME="$LAB" \
        "$PHP" -S "127.0.0.1:$PORT_HTTP" \
        -t "$CURRENT/public" "$LAB/router.php"
) > "$LAB/http-server.log" 2>&1 < /dev/null &
pid="$!"
printf '%s\n' "$pid" > "$LAB/server.pid"

ready=0
for i in $(seq 1 60); do
    response="$(curl --noproxy '*' -s -o /dev/null -w '%{http_code}' \
       --max-time 1 "http://127.0.0.1:$PORT_HTTP/login" 2>/dev/null || true)"
    if [[ "$response" == 200 ]]; then ready=1; break; fi
    kill -0 "$pid" 2>/dev/null || break
    sleep 0.15
done
[[ "$ready" == 1 ]] || fail "Private PHP HTTP bootstrap failed"

cat > "$PROXY/nginx.conf" <<EOF
worker_processes 1;
pid $PROXY/nginx.pid;
error_log $PROXY/error.log notice;
events { worker_connections 256; }
http {
    access_log $PROXY/access.log;
    server {
        listen 127.0.0.1:$PORT_HTTPS ssl;
        server_name 127.0.0.1;
        ssl_certificate $LAB/tls/cert.pem;
        ssl_certificate_key $LAB/tls/key.pem;
        ssl_protocols TLSv1.2 TLSv1.3;
        add_header X-Content-Type-Options nosniff always;
        client_max_body_size 12m;
        location / {
            proxy_pass http://127.0.0.1:$PORT_HTTP;
            proxy_set_header Host \$http_host;
            proxy_set_header X-Forwarded-Proto https;
            proxy_set_header X-Forwarded-Host \$http_host;
            proxy_read_timeout 30s;
        }
    }
}
EOF
chmod 600 "$PROXY/nginx.conf"
"$NGINX" -t -p "$PROXY/" -c "$PROXY/nginx.conf" ||
    fail "Private HTTPS Nginx syntax failure"
"$NGINX" -p "$PROXY/" -c "$PROXY/nginx.conf" ||
    fail "Private HTTPS Nginx launch failed"

response="$(curl --noproxy '*' -ks -o /dev/null -w '%{http_code}' \
    --max-time 10 "https://127.0.0.1:$PORT_HTTPS/login")" ||
    fail "Private HTTPS login failed"
[[ "$response" == 200 ]] || fail "HTTPS login returned $response"

echo "STAGING_HTTPS_LOGIN=200_PASS"
echo "PRIVATE_HTTPS_BIND=127.0.0.1:$PORT_HTTPS"
echo "TLS_CERTIFICATE=SHORT_LIVED_SELF_SIGNED"
echo "TLS_PUBLIC_TRUST_VALIDATION=STILL_REQUIRED"
echo "STAGING_CREDENTIALS=$LAB/shared/credentials.json"
echo "PREVIEW_URL=https://127.0.0.1:$PORT_HTTPS/login"
echo "SSH_TUNNEL=ssh -N -L $PORT_HTTPS:127.0.0.1:$PORT_HTTPS root@YOUR_SERVER"
echo "GLOBAL_NGINX_CONFIG=UNCHANGED"
echo "PRODUCTION_DATABASE_CRON_AND_VHOST=UNCHANGED"
