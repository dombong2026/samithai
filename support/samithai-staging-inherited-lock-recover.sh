#!/usr/bin/env bash
# One-time recovery from a staging HTTPS flock inherited by a long-running child.
# Never touches the production website, global Nginx, live DB, or production cron.
# --inspect: read only. --stop: terminate only verified private staging processes.
set -Eeuo pipefail
set +x
umask 077

if [[ $# -ne 1 || ( "$1" != --inspect && "$1" != --stop ) ]]; then
  echo "Usage: bash support/samithai-staging-inherited-lock-recover.sh --inspect|--stop" >&2
  exit 2
fi
MODE="$1"
LAB="/var/lib/samithai-editorial-staging-20261112"
ORIGINAL="$LAB/releases/candidate"
STAGED="$LAB/releases/pr6-faf8bd2b4335"
HTTP_PIDFILE="$LAB/server.pid"
TLS_PIDFILE="$LAB/tls-proxy.pid"
HTTPS_LOCK="/run/lock/samithai-editorial-staging-https.lock"
HTTP_PORT=8331
TLS_PORT=8843

fail() {
  echo "BLOCKED=$*" >&2
  echo "STAGING_STATE=PRESERVED"
  echo "PRODUCTION_DATABASE_NGINX_CRON=UNCHANGED"
  exit 3
}

[[ "$(id -u)" == 0 ]] || fail "Requires root to verify process identities"
for name in ps ss flock git curl realpath; do
  command -v "$name" >/dev/null || fail "Missing $name"
done
[[ -d "$LAB" && ! -L "$LAB" &&
   -d "$ORIGINAL" && ! -L "$ORIGINAL" &&
   -d "$STAGED" && ! -L "$STAGED" &&
   -L "$LAB/current" &&
   "$(realpath -e "$LAB/current")" == "$ORIGINAL" ]] ||
     fail "Unexpected active staging pointer"
[[ "$(git -C "$ORIGINAL" rev-parse HEAD)" == "54c92ba0817b31673e81972c1d7beebe2bf9f17e" ]] ||
     fail "Unexpected old staging commit"
[[ "$(git -C "$STAGED" rev-parse HEAD)" == "faf8bd2b4335f47715c41e29dff25c2f5e75ca31" ]] ||
     fail "Unexpected new staged commit"
[[ -f "$LAB/shared/staging.sqlite" && ! -L "$LAB/shared/staging.sqlite" &&
   -f "$STAGED/staging-release.json" ]] ||
     fail "Existing synthetic staging dataset or staged manifest unavailable"
[[ ! -e "$LAB/proxy/nginx.pid" && ! -L "$LAB/proxy/nginx.pid" ]] ||
     fail "Isolated OpenResty PID returned; requires separate review"
for pidfile in "$HTTP_PIDFILE" "$TLS_PIDFILE"; do
  [[ -f "$pidfile" && ! -L "$pidfile" &&
    "$(stat -c '%u' "$pidfile")" == 0 ]] ||
    fail "Missing, symlinked or non-root-owned staging PID file"
done

HTTP_PID="$(cat "$HTTP_PIDFILE")"
TLS_PID="$(cat "$TLS_PIDFILE")"
[[ "$HTTP_PID" =~ ^[1-9][0-9]*$ && "$TLS_PID" =~ ^[1-9][0-9]*$ &&
    "$HTTP_PID" != "$TLS_PID" ]] ||
     fail "Invalid or duplicated recorded staging PIDs"
kill -0 "$HTTP_PID" 2>/dev/null || fail "Private HTTP PID is no longer running"
kill -0 "$TLS_PID" 2>/dev/null || fail "Private HTTPS PID is no longer running"

HTTP_ARGS="$(ps -p "$HTTP_PID" -o args= 2>/dev/null || true)"
TLS_ARGS="$(ps -p "$TLS_PID" -o args= 2>/dev/null || true)"
HTTP_PGID="$(ps -p "$HTTP_PID" -o pgid= 2>/dev/null | tr -d ' ' || true)"
TLS_PGID="$(ps -p "$TLS_PID" -o pgid= 2>/dev/null | tr -d ' ' || true)"
[[ "$HTTP_ARGS" == *"127.0.0.1:$HTTP_PORT"* &&
   "$HTTP_ARGS" == *"$LAB/router.php"* &&
   "$HTTP_PGID" == "$HTTP_PID" ]] ||
     fail "Private HTTP process/PGID does not match recorded staging launcher"
[[ "$TLS_ARGS" == *"python3 $LAB/tls-proxy.py"* &&
   "$TLS_PGID" == "$TLS_PID" ]] ||
     fail "Private TLS process/PGID does not match recorded staging launcher"

# Confirm the expected loopback addresses still answer and are the sole
# listeners. Never terminate a non-staging service on the same TCP ports.
python3 - "$HTTP_PORT" "$TLS_PORT" "$HTTP_PGID" "$TLS_PID" <<'PY'
import re,subprocess,sys
http,tls,hpgid,tpid=map(int,sys.argv[1:])
lines=subprocess.check_output(['ss','-H','-ltnp'],text=True).splitlines()
for port in (http,tls):
    selected=[x for x in lines if re.search(r':'+str(port)+r'\s',x)]
    if len(selected)!=1:
        raise SystemExit('BLOCKED=EXPECTED_ONE_STAGING_LISTENER_PER_PORT:'+str(port))
    line=selected[0]
    if not (re.search(r'127[.]0[.]0[.]1:'+str(port)+r'\s',line)):
        raise SystemExit('BLOCKED=LISTENER_IS_NOT_LOOPBACK:'+str(port))
    ids=[int(v) for v in re.findall(r'pid=(\d+)',line)]
    if not ids:
        raise SystemExit('BLOCKED=LISTENER_PID_NOT_VISIBLE:'+str(port))
    for pid in ids:
        pgid=int(subprocess.check_output(['ps','-p',str(pid),'-o','pgid='],text=True).strip())
        if port==http and pgid!=hpgid:
            raise SystemExit('BLOCKED=HTTP_LISTENER_NOT_IN_STAGING_PROCESS_GROUP')
        if port==tls and pid!=tpid:
            raise SystemExit('BLOCKED=TLS_LISTENER_PID_NOT_THE_RECORDED_PROXY')
print('PRIVATE_HTTP_HTTPS_LISTENERS=VERIFIED')
PY

CODE="$(curl --noproxy '*' -skS --max-time 5 -o /dev/null \
  -w '%{http_code}' 'https://127.0.0.1:8843/login' 2>/dev/null || true)"
[[ "$CODE" == 200 ]] || fail "Expected functional private HTTPS login, got $CODE"
echo "PRIVATE_STAGING_PROCESSES=IDENTITY_VERIFIED"
echo "PRIVATE_HTTPS_LOGIN=200_PASS"

# It is safe to *check* the old lock without acquiring/holding it.
if (exec 8>"$HTTPS_LOCK"; flock -n 8); then
    echo "STAGING_HTTPS_OPERATOR_LOCK=AVAILABLE"
else
    echo "STAGING_HTTPS_OPERATOR_LOCK=HELD"
fi

if [[ "$MODE" == --inspect ]]; then
    echo "RECOVERY_INSPECTION=PASS"
    echo "PRODUCTION_DATABASE_NGINX_CRON=UNCHANGED"
    exit 0
fi

exec 7>/run/lock/samithai-editorial-staging-orphaned-lock-recovery.lock
flock -n 7 || fail "Concurrent targeted staging recovery"

echo "========== STOP ONLY VERIFIED PRIVATE STAGING SERVICES =========="
kill -TERM "$TLS_PID" || fail "Private HTTPS process termination failed"
kill -TERM -- "-$HTTP_PGID" || fail "Private HTTP process-group termination failed"

for i in $(seq 1 50); do
  if [[ -z "$(ss -H -ltn '( sport = :8331 or sport = :8843 )')" ]]; then
    break
  fi
  sleep 0.2
done
[[ -z "$(ss -H -ltn '( sport = :8331 or sport = :8843 )')" ]] ||
   fail "Private ports did not release; preserve PID files for diagnosis"

# Release the old lock only by stopping the verified staging children.
# Never delete, truncate, or bypass an owned live lock.
exec 8>"$HTTPS_LOCK"
flock -n 8 || fail "HTTPS operator flock still held; other process requires inspection"
flock -u 8

rm -- "$TLS_PIDFILE" "$HTTP_PIDFILE"
echo "STAGING_PRIVATE_HTTP_AND_HTTPS=STOPPED"
echo "STAGING_HTTPS_OPERATOR_FLOCK=RELEASED"
echo "STAGING_ACTIVE_POINTER=OLD_CANDIDATE_UNCHANGED"
echo "STAGING_SHARED_SQLITE_AND_MEDIA=UNCHANGED"
echo "PRODUCTION_DATABASE_NGINX_CRON=UNCHANGED"
