#!/usr/bin/env bash
# Version-aware, read-only preflight or encrypted snapshot of current Samith AI.
# No migrations, production config changes, media deletion or remote transfer.
set -Eeuo pipefail
set +x
umask 077

[[ $# == 1 && ( "$1" == --check || "$1" == --snapshot ) ]] || {
  echo "Usage: bash script.sh --check|--snapshot" >&2
  exit 2
}
MODE="$1"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
OUT="/root/SamithAI-Upgrade-Evidence"
VHOST="/www/server/panel/vhost/nginx/samithai.com.conf"
PHP="/www/server/php/85/bin/php"
MYSQL="/www/server/mysql/bin/mysql"
DUMP="/www/server/mysql/bin/mysqldump"

fail() { echo "BACKUP_BLOCKED=$*" >&2; exit 3; }
[[ "$(id -u)" == 0 && -x "$PHP" && -x "$MYSQL" && -x "$DUMP" ]] ||
    fail "Operator and PHP/MySQL binary identity"
for cmd in runuser python3 sha256sum tar gzip gpg flock git stat; do
    command -v "$cmd" >/dev/null || fail "Missing $cmd"
done
[[ -d "$APP" && ! -L "$APP" && -f "$APP/artisan" &&
    -f "$APP/vendor/autoload.php" && -f "$APP/.env" &&
    -f "$APP/storage/app/installed.lock" &&
    -d "$APP/storage/app/private" && ! -L "$APP/storage/app" &&
    -f "$VHOST" && ! -L "$VHOST" ]] ||
    fail "Production release layout unexpectedly changed"
[[ -d "$OUT" && ! -L "$OUT" && "$(stat -c '%u:%a' "$OUT")" == "0:700" ]] ||
    fail "Missing root-only recovery evidence folder"
grep -Fq "root $APP/public;" "$VHOST" ||
    fail "Production Nginx root changed"

# Laravel's read-only recovery command is the authority for the *installed*
# inventory version. A GitHub branch can be newer than the live release.
INV_CHECK="$(mktemp /root/samithai-live-inventory-check.XXXXXXXX)"
RUN=""
CNF=""
ENC=""
SUCCESS=0
cleanup() {
    rc=$?
    trap - EXIT
    unset PASS CONFIRM
    exec 3<&- 2>/dev/null || true
    [[ -z "$CNF" ]] || rm -f -- "$CNF"
    [[ -z "$RUN" || ! -d "$RUN" ]] || rm -rf -- "$RUN"
    rm -f -- "$INV_CHECK"
    if [[ "$SUCCESS" == 0 && -n "$ENC" ]]; then
        rm -f -- "$ENC" "$ENC.sha256"
    fi
    exit "$rc"
}
trap cleanup EXIT

(cd "$APP"; runuser -u www -- "$PHP" artisan samith:recovery-inventory --json --strict) \
    > "$INV_CHECK" || fail "Live read-only inventory command failed"
INVENTORY_VERSION="$(python3 - "$INV_CHECK" <<'PY'
import json,sys
with open(sys.argv[1]) as f: d=json.load(f)
fmt=d.get('format','')
mapping={
    'samith-recovery-inventory/v2':('v2',13),
    'samith-recovery-inventory/v3':('v3',14),
    'samith-recovery-inventory/v4':('v4',15),
}
if fmt not in mapping:
    raise SystemExit('BLOCKED=UNSUPPORTED_LIVE_INVENTORY_VERSION')
version,count=mapping[fmt]
tables=d.get('tables',{})
if (d.get('database_driver')!='mysql'
    or len(tables)!=count
    or any(not x.get('present') for x in tables.values())
    or d.get('media',{}).get('missing')!=0
    or d.get('media',{}).get('checksum_mismatch')!=0):
    raise SystemExit('BLOCKED=LIVE_INVENTORY_OR_MEDIA_UNHEALTHY')
print(version)
PY
)" || fail "Live inventory format or integrity unsupported"

echo "LIVE_PRODUCTION_INVENTORY=$INVENTORY_VERSION"
echo "LIVE_PRODUCTION_MEDIA_INTEGRITY=PASS"
(cd "$APP"; runuser -u www -- "$PHP" artisan migrate:status --no-ansi >/dev/null) ||
    fail "Current Laravel schema cannot be inspected"

if [[ "$MODE" == --check ]]; then
    echo "VERSION_AWARE_BACKUP_PREFLIGHT=PASS"
    echo "ENCRYPTED_BACKUP=NOT_CREATED"
    echo "PRODUCTION_MYSQL_MEDIA_NGINX_CRON=UNCHANGED"
    exit 0
fi

[[ -r /dev/tty ]] || fail "Interactive TTY required to protect backup passphrase"
exec 9>/run/lock/samithai-versioned-encrypted-backup.lock
flock -n 9 || fail "Another protected backup is in progress"

RUN="$(mktemp -d "$OUT/samithai-recovery-snapshot.XXXXXXXX")"
chmod 700 "$RUN"
touch "$RUN/INCOMPLETE"
CNF="$(mktemp /root/samithai-mysql-options.XXXXXXXX)"
chmod 600 "$CNF"

echo "========== VERIFY LIVE MYSQL AUTHORIZATION =========="
DB_NAME="$("$PHP" /dev/stdin "$APP" "$CNF" <<'PHP'
<?php
$root=$argv[1]; $cnf=$argv[2];
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c=\Illuminate\Support\Facades\DB::connection()->getConfig();
if (config('app.env')!=='production'
    || config('app.url')!=='https://samithai.com'
    || config('database.default')!=='mysql'
    || ($c['driver']??'')!=='mysql'
    || config('filesystems.default')!=='local') exit(4);
$db=(string)($c['database']??'');
if (!preg_match('/^[A-Za-z0-9_]+$/D',$db)) exit(4);
$quote=static function ($value): string {
    $value=(string)$value;
    if (preg_match('/[\r\n]/',$value)) throw new \RuntimeException('Invalid credential delimiter');
    return '"'.str_replace(['\\','"'],['\\\\','\\"'],$value).'"';
};
$body="[client]\n";
foreach (['host','port','username','password'] as $key) {
    if ($key==='host' && !empty($c['unix_socket'])) continue;
    $name=($key==='username')?'user':$key;
    $body.=$name.'='.$quote($c[$key]??'')."\n";
}
if (!empty($c['unix_socket'])) $body.='socket='.$quote($c['unix_socket'])."\n";
if (file_put_contents($cnf,$body,LOCK_EX)!==strlen($body)) exit(4);
chmod($cnf,0600);
echo $db;
PHP
)" || fail "Live Laravel production database settings rejected"

[[ -n "$DB_NAME" && "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]] ||
    fail "Unsafe database identifier"
"$MYSQL" --defaults-extra-file="$CNF" --batch --skip-column-names \
    --execute='SELECT 1' >/dev/null || fail "Live database read check failed"
NON_INNODB="$("$MYSQL" --defaults-extra-file="$CNF" --batch --skip-column-names \
    --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_type='BASE TABLE' AND (engine IS NULL OR engine <> 'InnoDB')" | tail -1)"
[[ "$NON_INNODB" == 0 ]] ||
    fail "Nontransactional MySQL tables require separate consistency planning"
echo "MYSQL_TRANSACTIONAL_SOURCE=VERIFIED"

# Source inventory is checked immediately before and after capturing both
# SQL and private-media state. Fail closed if either changes during capture.
cp -- "$INV_CHECK" "$RUN/inventory-before.json"
"$DUMP" --defaults-extra-file="$CNF" --single-transaction --quick \
    --skip-lock-tables --no-tablespaces --set-gtid-purged=OFF \
    --default-character-set=utf8mb4 --hex-blob \
    --routines --events --triggers "$DB_NAME" | gzip -1 > "$RUN/mysql.sql.gz" ||
    fail "Transactional MySQL dump failed"

tar -C "$APP" -czf "$RUN/application-state.tar.gz" -- .env storage/app ||
    fail "Application secrets/private media snapshot failed"
gzip -t "$RUN/mysql.sql.gz" || fail "Compressed SQL check failed"
tar -tzf "$RUN/application-state.tar.gz" >/dev/null ||
    fail "Private state tar verification failed"
(cd "$APP"; runuser -u www -- "$PHP" artisan samith:recovery-inventory --json --strict) \
    > "$RUN/inventory-after.json" ||
    fail "Post-snapshot source inventory failed"

python3 - "$RUN/inventory-before.json" "$RUN/inventory-after.json" "$INVENTORY_VERSION" <<'PY'
import json,sys
with open(sys.argv[1]) as f: before=json.load(f)
with open(sys.argv[2]) as f: after=json.load(f)
if before!=after or before.get('format')!='samith-recovery-inventory/'+sys.argv[3]:
    raise SystemExit('BLOCKED=SOURCE_CHANGED_DURING_BACKUP')
print('LIVE_INVENTORY_BEFORE_AFTER=IDENTICAL')
PY

python3 - "$RUN/backup-metadata.json" "$INVENTORY_VERSION" <<'PY'
import datetime,json,os,sys
data={"format":"samithai/versioned-backup/v1","inventory_version":sys.argv[2],
      "created_utc":datetime.datetime.now(datetime.timezone.utc).isoformat(),
      "source":"pinned-samithai-production","contains_private_application_state":True,
      "independent_restore_verified":False}
fd=os.open(sys.argv[1],os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
with os.fdopen(fd,"w") as f: json.dump(data,f,indent=2)
PY
(
  cd "$RUN"
  sha256sum mysql.sql.gz application-state.tar.gz inventory-before.json \
     inventory-after.json backup-metadata.json > SHA256SUMS
  sha256sum -c SHA256SUMS >/dev/null
)
chmod 600 "$RUN"/*
rm "$RUN/INCOMPLETE"

echo "========== ENCRYPT VERIFIED SNAPSHOT =========="
IFS= read -r -s -p 'New backup passphrase (20 or more characters): ' PASS </dev/tty
printf '\n' >/dev/tty
IFS= read -r -s -p 'Confirm new backup passphrase: ' CONFIRM </dev/tty
printf '\n' >/dev/tty
PASS_SIZE="$(printf '%s' "$PASS" | wc -c)"
[[ "$PASS" == "$CONFIRM" && "$PASS_SIZE" -ge 20 ]] ||
    fail "Backup encryption passphrase mismatch or too short"
unset CONFIRM PASS_SIZE
ENC="$OUT/samithai-recovery-$INVENTORY_VERSION-$(date -u +%Y%m%dT%H%M%SZ).tar.gpg"
[[ ! -e "$ENC" && ! -L "$ENC" ]] || fail "Encrypted artifact already exists"
exec 3<<<"$PASS"
tar -C "$OUT" -cf - "$(basename "$RUN")" |
  gpg --batch --pinentry-mode loopback --no-symkey-cache --passphrase-fd 3 \
      --symmetric --cipher-algo AES256 --output "$ENC" ||
    fail "AES256 encrypted backup failed"
exec 3<&-
exec 3<<<"$PASS"
gpg --batch --quiet --pinentry-mode loopback --no-symkey-cache --passphrase-fd 3 \
    --decrypt --output /dev/null "$ENC" ||
    fail "Encrypted backup decrypt-validation failed"
exec 3<&-
unset PASS

(
  cd "$OUT"
  sha256sum "$(basename "$ENC")" > "$(basename "$ENC").sha256"
  sha256sum -c "$(basename "$ENC").sha256" >/dev/null
)
chmod 600 "$ENC" "$ENC.sha256"
SUCCESS=1
echo "VERSIONED_ENCRYPTED_BACKUP=VERIFIED"
echo "LIVE_INVENTORY_VERSION=$INVENTORY_VERSION"
echo "ENCRYPTED_BACKUP=$ENC"
echo "BACKUP_SHA256=$ENC.sha256"
echo "PLAINTEXT_SNAPSHOT=CLEANED_ON_EXIT"
echo "OFFSITE_COPY=NOT_VERIFIED"
echo "INDEPENDENT_MYSQL_RESTORE=NOT_PERFORMED"
echo "PRODUCTION_MYSQL_MEDIA_NGINX_CRON=UNCHANGED"
