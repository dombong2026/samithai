#!/usr/bin/env bash
# Prepare a *remote*, separate MySQL 8 QA instance connection through SSH,
# without changing any production database, MySQL service, Nginx, or cron.
#
# --probe: inspect local loopback SSH tunnel, no credentials or writes.
# --configure: prompt privately for QA-only credentials and run restore --check.
# --verify: check existing QA config and restore --check, no credentials output.
set -Eeuo pipefail
set +x
umask 077

if [[ $# != 1 || ( "$1" != --probe && "$1" != --configure && "$1" != --verify ) ]]; then
    echo 'Usage: bash script.sh --probe|--configure|--verify' >&2
    exit 2
fi
MODE="$1"
ROOT_DIR="/root/SamithAI-PR6-Release-Readiness"
CONF="$ROOT_DIR/qa-remote-ssh-tunnel.cnf"
RESTORE="$ROOT_DIR/versioned-independent-restore.sh"
BACKUP="/root/SamithAI-Upgrade-Evidence/samithai-recovery-v4-20261112T165749Z.tar.gpg"
MYSQL="/www/server/mysql/bin/mysql"
PORT=33079
RESTORE_BLOB="52c5060aa4d87ad0ec5ff7ec281a56cd129c3462"

fail() {
    echo "QA_TUNNEL_PREFLIGHT_BLOCKED=$*" >&2
    echo "PRODUCTION_MYSQL_MEDIA_NGINX_CRON=UNCHANGED"
    exit 3
}

[[ "$(id -u)" == 0 && -x "$MYSQL" ]] || fail "Root and aaPanel mysql client required"
for binary in git python3 stat ss; do
    command -v "$binary" >/dev/null || fail "Missing $binary"
done
[[ -d "$ROOT_DIR" && ! -L "$ROOT_DIR" &&
   "$(stat -c '%u:%a' "$ROOT_DIR")" == "0:700" &&
   -f "$BACKUP" && ! -L "$BACKUP" &&
   -f "$RESTORE" && ! -L "$RESTORE" &&
   "$(git hash-object "$RESTORE")" == "$RESTORE_BLOB" ]] ||
    fail "QA operator, backup or restricted workspace identity changed"

echo "========== CHECK REMOTE MYSQL SSH TUNNEL =========="
if python3 - "$PORT" <<'PY'
import re,subprocess,sys
port=int(sys.argv[1])
lines=subprocess.check_output(
    ['ss','-H','-ltnp','( sport = :'+str(port)+' )'],
    text=True).splitlines()
if not lines:
    print('REMOTE_QA_SSH_TUNNEL=NOT_CONNECTED')
    raise SystemExit(4)
for line in lines:
    if not re.search(r'127[.]0[.]0[.]1:'+str(port)+r'\s',line):
        raise SystemExit('BLOCKED=QA_TUNNEL_NOT_LOOPBACK')
    if not re.search(r'users:\(\("ssh",pid=\d+',line):
        raise SystemExit('BLOCKED=QA_TUNNEL_NOT_OPENSSH')
print('REMOTE_QA_SSH_TUNNEL=LOCALHOST_ONLY')
PY
then
    :
else
    status=$?
    if [[ "$MODE" == --probe && "$status" == 4 ]]; then
        echo "QA_MYSQL_CREDENTIALS=NOT_CONFIGURED"
        echo "INDEPENDENT_RESTORE=NOT_PERFORMED"
        echo "NEXT=Provision a dedicated remote MySQL 8 QA host and open an SSH tunnel on local port 33079"
        exit 0
    fi
    fail "SSH tunnel not established or not trusted"
fi

if [[ "$MODE" == --probe ]]; then
    echo "REMOTE_MYSQL_TCP_TUNNEL=AVAILABLE"
    echo "PRODUCTION_DATABASE=UNCHANGED"
    exit 0
fi

if [[ "$MODE" == --configure ]]; then
    [[ ! -e "$CONF" && ! -L "$CONF" ]] ||
        fail "QA configuration already exists; use --verify"
    [[ -r /dev/tty ]] || fail "Interactive TTY required"
    printf 'Dedicated QA-only MySQL username: ' >/dev/tty
    IFS= read -r USERNAME </dev/tty
    [[ "$USERNAME" =~ ^[A-Za-z0-9_]{1,64}$ ]] ||
        fail "Unsafe QA username"
    IFS= read -r -s -p 'QA-only MySQL password (hidden): ' PASSWORD </dev/tty
    printf '\n' >/dev/tty
    [[ -n "$PASSWORD" && "$PASSWORD" != *$'\n'* &&
       "$PASSWORD" != *$'\r'* ]] ||
        fail "Invalid QA password"
    {
        printf '[client]\n'
        printf 'protocol=tcp\n'
        printf 'host=127.0.0.1\n'
        printf 'port=%s\n' "$PORT"
        printf 'user="%s"\n' "$USERNAME"
        printf 'password='
        printf '%s' "$PASSWORD" | python3 -c 'import sys; p=sys.stdin.read(); print(chr(34)+p.replace(chr(92),chr(92)*2).replace(chr(34),chr(92)+chr(34))+chr(34))'
        printf 'default-character-set=utf8mb4\n'
        printf 'connect-timeout=5\n'
    } > "$CONF"
    chmod 600 "$CONF"
    unset PASSWORD USERNAME
fi

[[ -f "$CONF" && ! -L "$CONF" &&
   "$(stat -c '%u:%a' "$CONF")" == "0:600" ]] ||
    fail "Root-only QA client config invalid"
# Verify exact destination. The restore operator independently cross-checks
# @@server_uuid against production and requires event_scheduler OFF.
python3 - "$CONF" "$PORT" <<'PY'
import configparser,sys
p=configparser.ConfigParser(interpolation=None)
p.read(sys.argv[1])
c=p['client']
if (c.get('host')!='127.0.0.1'
    or c.get('port')!=sys.argv[2]
    or c.get('protocol')!='tcp'):
    raise SystemExit('BLOCKED=QA_CREDENTIALS_NOT_BOUND_TO_SSH_TUNNEL')
print('QA_MYSQL_CONFIG=ROOT_ONLY_LOOPBACK')
PY

# --check never decrypts the archive, creates a DB, or modifies live MySQL.
bash "$RESTORE" --check "$BACKUP" "$CONF" ||
    fail "Distinct QA MySQL instance or credentials not accepted"
echo "DISTINCT_QA_MYSQL_INSTANCE=PASS"
echo "QA_EVENT_SCHEDULER=SAFE"
echo "INDEPENDENT_RESTORE=NOT_PERFORMED"
echo "PRODUCTION_MYSQL_MEDIA_NGINX_CRON=UNCHANGED"
