#!/usr/bin/env bash
# Samith AI current-v3 encrypted recovery snapshot.
# --check reads only; --snapshot creates a local AES256 encrypted backup.
# This does not restore, migrate, deploy, or transfer files offsite.
set +x
set -Eeuo pipefail
umask 077

if [[ $# -ne 1 || ( "$1" != '--check' && "$1" != '--snapshot' ) ]]; then
  echo 'Usage: production-local-backup.sh --check|--snapshot' >&2
  exit 2
fi
MODE="$1"
APP='/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49'
OUT='/root/SamithAI-Upgrade-Evidence'
PHP='/www/server/php/85/bin/php'
MYSQL='/www/server/mysql/bin/mysql'
DUMP='/www/server/mysql/bin/mysqldump'
VHOST='/www/server/panel/vhost/nginx/samithai.com.conf'

echo '========== PRODUCTION LOCAL-BACKUP PREFLIGHT =========='
test "$(id -u)" = 0
for binary in runuser python3 gzip tar sha256sum flock git gpg; do
  command -v "$binary" >/dev/null || { echo "BLOCKED: missing $binary"; exit 1; }
done
test -x "$PHP" && test -x "$MYSQL" && test -x "$DUMP"
test -f "$APP/artisan" && test -f "$APP/vendor/autoload.php"
test -f "$APP/.env" && test -f "$APP/storage/app/installed.lock"
test -f "$VHOST" && test -d "$APP/storage/app/private"
test -d "$OUT" && test ! -L "$OUT"
test "$(stat -c '%u' "$OUT")" = 0
test "$(stat -c '%a' "$OUT")" = 700
grep -Fq "root $APP/public;" "$VHOST"

(
  cd "$APP"
  runuser -u www -- "$PHP" artisan samith:recovery-inventory --json --strict
) | python3 -c '
import json,sys
d=json.load(sys.stdin)
if (d.get("format")!="samith-recovery-inventory/v3"
  or d.get("database_driver")!="mysql"
  or len(d.get("tables",{}))!=14
  or d.get("media",{}).get("missing")!=0
  or d.get("media",{}).get("checksum_mismatch")!=0
  or not d.get("tables",{}).get("prompt_reviews",{}).get("present")):
    raise SystemExit("BLOCKED: current v3 database and media inventory is unhealthy")
print("CURRENT_PRODUCTION_V3=HEALTHY")
'

(cd "$APP"; runuser -u www -- "$PHP" artisan migrate:status --no-ansi >/dev/null)
"$DUMP" --version | head -1
echo 'MYSQL_AND_APPLICATION=READABLE'
if [[ "$MODE" == '--check' ]]; then
  echo 'LOCAL_ENCRYPTED_V3_BACKUP=NOT_CREATED'
  echo 'OFFSITE_RESTORE=NOT_VERIFIED'
  echo 'PRODUCTION=UNCHANGED'
  exit 0
fi

exec 9>/root/samithai-local-backup.lock
flock -n 9 || { echo 'BLOCKED: concurrent backup'; exit 1; }

RUN="$(mktemp -d "$OUT/current-v3-snapshot.XXXXXXXX")"
touch "$RUN/INCOMPLETE"
CNF="$(mktemp /root/samithai-mysql-options.XXXXXXXX)"
ENC=""
cleanup() {
  code=$?
  trap - EXIT
  unset PASS CONFIRM
  exec 3<&- 2>/dev/null || true
  rm -f -- "$CNF"
  if [[ -n "$RUN" && -d "$RUN" ]]; then rm -rf -- "$RUN"; fi
  if [[ "$code" -ne 0 && -n "$ENC" ]]; then rm -f -- "$ENC" "$ENC.sha256"; fi
  exit "$code"
}
trap cleanup EXIT

echo '========== PREPARE PRIVATE MYSQL AUTHENTICATION =========='
DB_NAME="$(
  "$PHP" /dev/stdin "$APP" "$CNF" <<'PHP'
<?php
$root=$argv[1]; $cnf=$argv[2];
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$config=\Illuminate\Support\Facades\DB::connection()->getConfig();
if (config('app.env') !== 'production'
    || config('app.url') !== 'https://samithai.com'
    || config('database.default') !== 'mysql'
    || ($config['driver'] ?? '') !== 'mysql'
    || config('filesystems.default') !== 'local') {
    fwrite(STDERR, "BLOCKED: unexpected app/database/storage configuration\n");
    exit(1);
}
$db=(string)($config['database'] ?? '');
if (!preg_match('/^[A-Za-z0-9_]+$/D',$db)) {
    fwrite(STDERR, "BLOCKED: database name requires manual review\n");
    exit(1);
}
$quote=static function ($v): string {
    $v=(string)$v;
    if (preg_match('/[\r\n]/',$v)) throw new RuntimeException('Unsafe database setting');
    return '"'.str_replace(['\\','"'],['\\\\','\\"'],$v).'"';
};
$body="[client]\n";
foreach (['host','port','username','password'] as $key) {
    if ($key==='host' && !empty($config['unix_socket'])) continue;
    $name=$key==='username' ? 'user' : $key;
    $body.=$name.'='.$quote($config[$key] ?? '')."\n";
}
if (!empty($config['unix_socket'])) {
    $body.='socket='.$quote($config['unix_socket'])."\n";
}
if (file_put_contents($cnf,$body,LOCK_EX)!==strlen($body)) {
    throw new RuntimeException('MySQL options could not be written');
}
chmod($cnf,0600);
echo $db;
PHP
)"
test -n "$DB_NAME"
# Passwords are never printed or put on the process command line.
"$MYSQL" --defaults-extra-file="$CNF" --batch --skip-column-names \
  --execute='SELECT 1' >/dev/null
NON_INNODB="$("$MYSQL" --defaults-extra-file="$CNF" --batch --skip-column-names \
  --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_type='BASE TABLE' AND (engine IS NULL OR engine <> 'InnoDB')" | tail -1)"
if [[ "$NON_INNODB" != '0' ]]; then
  echo 'BLOCKED: non-transactional tables require separate backup consistency planning'
  exit 1
fi
echo 'MYSQL_AUTH_AND_INNODB=VERIFIED'

echo '========== BEFORE-BACKUP INTEGRITY =========='
(cd "$APP"; runuser -u www -- "$PHP" artisan samith:recovery-inventory --json --strict) \
  > "$RUN/inventory-before.json"

echo '========== CREATE SQL AND PRIVATE-STATE SNAPSHOTS =========='
# Do not use --databases: restored SQL must not switch to the live database.
"$DUMP" --defaults-extra-file="$CNF" --single-transaction --quick \
  --skip-lock-tables --no-tablespaces --set-gtid-purged=OFF \
  --default-character-set=utf8mb4 --hex-blob \
  --routines --events --triggers "$DB_NAME" | gzip -1 > "$RUN/mysql.sql.gz"
tar -C "$APP" -czf "$RUN/application-state.tar.gz" -- .env storage/app

echo '========== VERIFY SNAPSHOT DATA =========='
test -s "$RUN/mysql.sql.gz"
test -s "$RUN/application-state.tar.gz"
gzip -t "$RUN/mysql.sql.gz"
tar -tzf "$RUN/application-state.tar.gz" >/dev/null
(cd "$APP"; runuser -u www -- "$PHP" artisan samith:recovery-inventory --json --strict) \
  > "$RUN/inventory-after.json"
python3 - "$RUN/inventory-before.json" "$RUN/inventory-after.json" <<'PY'
import json,sys
with open(sys.argv[1]) as f: before=json.load(f)
with open(sys.argv[2]) as f: after=json.load(f)
if before.get('format')!='samith-recovery-inventory/v3' or before!=after:
    raise SystemExit('BLOCKED: source recovery inventory changed during capture; repeat in a controlled window')
print('BEFORE_AFTER_INVENTORIES=MATCHED')
PY
(
  cd "$RUN"
  sha256sum mysql.sql.gz application-state.tar.gz inventory-before.json \
    inventory-after.json > SHA256SUMS
  sha256sum -c SHA256SUMS >/dev/null
)
chmod 600 "$RUN"/*
rm "$RUN/INCOMPLETE"
echo 'LOCAL_V3_SNAPSHOT_AND_MANIFEST=VERIFIED'

echo '========== AES-256 ENCRYPTION =========='
test -r /dev/tty
IFS= read -r -s -p 'Backup passphrase (20+ characters): ' PASS </dev/tty
printf '\n' > /dev/tty
IFS= read -r -s -p 'Confirm the passphrase: ' CONFIRM </dev/tty
printf '\n' > /dev/tty
if [[ "$PASS" != "$CONFIRM" || ${#PASS} -lt 20 ]]; then
  echo 'BLOCKED: passphrase mismatch or fewer than 20 characters'
  exit 1
fi
unset CONFIRM
ENC="$OUT/samithai-current-v3-$(date -u +%Y%m%dT%H%M%SZ).tar.gpg"
test ! -e "$ENC"
exec 3<<<"$PASS"
tar -C "$OUT" -cf - "$(basename "$RUN")" |
  gpg --batch --pinentry-mode loopback --no-symkey-cache --passphrase-fd 3 \
     --symmetric --cipher-algo AES256 --output "$ENC"
exec 3<&-
echo '========== VERIFY DECRYPTION AND CHECKSUM =========='
exec 3<<<"$PASS"
gpg --batch --quiet --pinentry-mode loopback --no-symkey-cache --passphrase-fd 3 \
     --decrypt --output /dev/null "$ENC"
exec 3<&-
unset PASS
(cd "$OUT"; sha256sum "$(basename "$ENC")" > "$(basename "$ENC").sha256"; sha256sum -c "$(basename "$ENC").sha256")
chmod 600 "$ENC" "$ENC.sha256"
rm -rf -- "$RUN"
echo '========== CURRENT V3 ENCRYPTED BACKUP VERIFIED =========='
echo "ENCRYPTED_BACKUP=$ENC"
echo "SHA256_MANIFEST=$ENC.sha256"
echo 'MYSQL_DUMP_AND_PRIVATE_STATE=VERIFIED'
echo 'V3_INVENTORY_BEFORE_AFTER=MATCHED'
echo 'AES256_ENCRYPTION_AND_DECRYPTION=VERIFIED'
echo 'TEMPORARY_PLAINTEXT=REMOVED'
echo 'OFFSITE_RESTORE=NOT_VERIFIED'
echo 'PRODUCTION_DB_ENV_NGINX=UNCHANGED'
