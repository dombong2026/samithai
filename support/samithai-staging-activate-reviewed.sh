#!/usr/bin/env bash
# Offline, reversible staging pointer switch; no live site change.
# Stop the private HTTP and TLS preview first. Never kill arbitrary PIDs.
set -Eeuo pipefail
set +x
umask 077
if [[ $# != 3 || ( "$1" != --check && "$1" != --activate && "$1" != --rollback ) ||
   ! "$2" =~ ^[a-f0-9]{40}$ || ! "$3" =~ ^[a-f0-9]{40}$ ]]; then
  echo "Usage: script.sh --check|--activate|--rollback PR6_SHA BASE_SHA" >&2
  exit 2
fi
MODE="$1"
HEAD="$2"
BASE="$3"
LAB="/var/lib/samithai-editorial-staging-20261112"
OLD="$LAB/releases/candidate"
NEW="$LAB/releases/pr6-$(printf '%s' "$HEAD" | cut -c1-12)"
CURRENT="$LAB/current"
STAMP="$LAB/pr6-stage-activation.json"
blocked(){ echo "BLOCKED=$*" >&2; echo "PRODUCTION=UNCHANGED"; exit 3; }

[[ "$(id -u)" == 0 ]] || blocked "Root required"
for binary in python3 git flock ss; do
  command -v "$binary" >/dev/null || blocked "Missing $binary"
done
[[ -d "$LAB" && ! -L "$LAB" &&
    -d "$NEW" && ! -L "$NEW" &&
    -d "$OLD" && ! -L "$OLD" &&
    -L "$CURRENT" &&
    -f "$NEW/staging-release.json" &&
    -L "$NEW/.env" &&
    "$(realpath "$NEW/.env")" == "$LAB/shared/.env" ]] ||
    blocked "Missing or unexpected staged release state"
[[ "$(git -C "$NEW" rev-parse HEAD)" == "$HEAD" ]] ||
    blocked "Staged source SHA mismatch"
python3 - "$NEW/staging-release.json" "$HEAD" "$BASE" <<'PY'
import json,sys
d=json.load(open(sys.argv[1]))
assert d.get('format')=='samithai/pr6-staged/v1'
assert d.get('commit')==sys.argv[2]
assert d.get('base')==sys.argv[3]
assert d.get('status')=='STAGED_ONLY'
assert d.get('production_mutations') is False
PY

active="$(realpath -e "$CURRENT")"
[[ "$active" == "$OLD" || "$active" == "$NEW" ]] ||
    blocked "Unexpected active staging pointer"
echo "CURRENT_STAGING_POINTER=$active"
echo "REVIEWED_STAGING_RELEASE=$NEW"

if [[ "$MODE" == --check ]]; then
  echo "OFFLINE_ACTIVATION=AVAILABLE_AFTER_PRIVATE_HTTP_AND_TLS_STOP"
  echo "PRODUCTION_POINTER=UNCHANGED"
  exit 0
fi

exec 9>/run/lock/samithai-editorial-staging-activation.lock
flock -n 9 || blocked "Concurrent staging activation"
for pidfile in "$LAB/server.pid" "$LAB/tls-proxy.pid" "$LAB/proxy/nginx.pid"; do
  [[ ! -e "$pidfile" && ! -L "$pidfile" ]] ||
      blocked "Staging service PID still present: $pidfile"
done
[[ -z "$(ss -H -ltn '( sport = :8331 or sport = :8843 )')" ]] ||
    blocked "Stop private loopback HTTP/HTTPS before switching"
[[ ! -L "$CURRENT.next" && ! -e "$CURRENT.next" ]] ||
    blocked "Unexpected pending activation symlink"

if [[ "$MODE" == --activate ]]; then
  [[ "$active" == "$OLD" ]] ||
    blocked "Expected original staging release to be active"
  [[ ! -e "$STAMP" && ! -L "$STAMP" ]] ||
    blocked "Activation checkpoint already exists"
  python3 - "$STAMP" "$HEAD" "$OLD" "$NEW" <<'PY'
import json,os,sys
data={"format":"samithai/staging-activation/v1","commit":sys.argv[2],
"rollback_target":sys.argv[3],"candidate_target":sys.argv[4],
"reversible_only_offline":True,"production_changed":False}
fd=os.open(sys.argv[1],os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
with os.fdopen(fd,'w') as f: json.dump(data,f,indent=2)
PY
  ln -s "$NEW" "$CURRENT.next"
  mv -Tf "$CURRENT.next" "$CURRENT"
  [[ "$(realpath -e "$CURRENT")" == "$NEW" ]] ||
    blocked "Atomic candidate symlink switch failed"
  echo "STAGING_POINTER_ACTIVATED=$HEAD"
else
  [[ "$active" == "$NEW" ]] ||
    blocked "Reviewed release is not currently active"
  [[ -f "$STAMP" && ! -L "$STAMP" ]] ||
    blocked "Missing staging activation checkpoint"
  python3 - "$STAMP" "$HEAD" "$OLD" "$NEW" <<'PY'
import json,sys
j=json.load(open(sys.argv[1]))
assert j.get('format')=='samithai/staging-activation/v1'
assert (j.get('commit'),j.get('rollback_target'),j.get('candidate_target'))==tuple(sys.argv[2:5])
assert j.get('production_changed') is False
PY
  ln -s "$OLD" "$CURRENT.next"
  mv -Tf "$CURRENT.next" "$CURRENT"
  [[ "$(realpath -e "$CURRENT")" == "$OLD" ]] ||
    blocked "Atomic rollback pointer switch failed"
  mv -- "$STAMP" "$STAMP.rolled-back"
  echo "STAGING_POINTER_ROLLED_BACK=OLD_CANDIDATE"
fi

echo "STAGING_SHARED_STORAGE=UNCHANGED"
echo "STAGING_SQLITE=UNCHANGED"
echo "PRIVATE_HTTP_AND_HTTPS=OFFLINE_RESTART_REQUIRED"
echo "PRODUCTION_MYSQL_MEDIA_NGINX_CRON=UNCHANGED"
