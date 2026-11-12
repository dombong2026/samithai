#!/usr/bin/env bash
# Independent VERSIONED AES256 backup restoration rehearsal; no live MySQL writes.
# Requires a separately provisioned, offline/non-public MySQL 8 QA INSTANCE.
# QA account must have CREATE DATABASE privilege on the QA instance only.
# Restored records may be sensitive; the QA database is not removed automatically.
set -Eeuo pipefail
set +x
umask 077

if [[ $# != 3 || ( "$1" != --check && "$1" != --restore ) ]]; then
  echo "Usage: script.sh --check|--restore /root/SamithAI-Upgrade-Evidence/ENCRYPTED.tar.gpg /root/QA_MYSQL.cnf" >&2
  exit 2
fi
MODE="$1"
ARCHIVE="$2"
QACNF="$3"
PROD="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
PHP="/www/server/php/85/bin/php"
MYSQL="/www/server/mysql/bin/mysql"
WORK=""

stop() { echo "RESTORE_BLOCKED=$*" >&2; exit 3; }
cleanup() {
  local rc=$?
  trap - EXIT
  [[ -z "$WORK" || ! -d "$WORK" ]] || rm -rf -- "$WORK"
  exit "$rc"
}
trap cleanup EXIT

[[ "$(id -u)" == 0 && -x "$PHP" && -x "$MYSQL" ]] || stop "Root and approved MySQL/PHP binaries required"
for binary in gpg sha256sum gzip python3 stat; do
  command -v "$binary" >/dev/null || stop "Missing $binary"
done
[[ "$ARCHIVE" =~ ^/root/SamithAI-Upgrade-Evidence/samithai-recovery-(v2|v3|v4)-[0-9]{8}T[0-9]{6}Z[.]tar[.]gpg$ &&
    -f "$ARCHIVE" && ! -L "$ARCHIVE" &&
    -f "$ARCHIVE.sha256" && ! -L "$ARCHIVE.sha256" &&
    "$(stat -c '%u' "$ARCHIVE")" == 0 ]] || stop "Unexpected encrypted backup path"
ARCHIVE_VERSION="${BASH_REMATCH[1]}"
[[ -f "$QACNF" && ! -L "$QACNF" &&
    "$(stat -c '%u' "$QACNF")" == 0 &&
    "$(stat -c '%a' "$QACNF")" == 600 ]] ||
    stop "Independent QA MySQL credentials must be root-owned mode 600"
[[ -f "$PROD/.env" && -f "$PROD/vendor/autoload.php" ]] ||
    stop "Known production identity missing"

(
  cd "$(dirname "$ARCHIVE")"
  sha256sum -c "$(basename "$ARCHIVE").sha256" >/dev/null
) || stop "Encrypted backup SHA256 mismatch"
echo "ENCRYPTED_BACKUP_SHA256=PASS"

# The restored QA server must be physically/logically distinct: disallow
# a second schema on the live MySQL INSTANCE even if its name differs.
PROD_UUID="$("$PHP" -r '
require $argv[1]."/vendor/autoload.php";
$app=require $argv[1]."/bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config("app.env")!=="production" ||
    \Illuminate\Support\Facades\DB::connection()->getDriverName()!=="mysql") exit(5);
echo \Illuminate\Support\Facades\DB::selectOne("SELECT @@server_uuid AS id")->id;
' "$PROD")" || stop "Production server-identity probe failed"

QA_UUID="$("$MYSQL" --defaults-extra-file="$QACNF" --batch --skip-column-names \
  --execute='SELECT @@server_uuid' | tail -1)" ||
    stop "Independent QA MySQL server not accessible"

[[ -n "$PROD_UUID" && -n "$QA_UUID" && "$PROD_UUID" != "$QA_UUID" ]] ||
    stop "QA and production MySQL are the same instance; independent restore REQUIRED"

EVENTS="$("$MYSQL" --defaults-extra-file="$QACNF" --batch --skip-column-names \
    --execute='SELECT @@event_scheduler' | tail -1)"
[[ "$EVENTS" == OFF || "$EVENTS" == DISABLED ]] ||
    stop "QA event_scheduler must be OFF or DISABLED"

echo "QA_MYSQL_DIFFERENT_SERVER_UUID=PASS"
echo "QA_EVENT_SCHEDULER=SAFE"
if [[ "$MODE" == --check ]]; then
  echo "ENCRYPTED_BACKUP=NOT_DECRYPTED"
  echo "INDEPENDENT_RESTORE=NOT_PERFORMED"
  echo "PRODUCTION_DATABASE=UNCHANGED"
  exit 0
fi

[[ -r /dev/tty ]] || stop "Operator TTY required to unlock encrypted backup"
command -v flock >/dev/null || stop "flock missing"
exec 9>/run/lock/samithai-independent-backup-restore.lock
flock -n 9 || stop "Another restore rehearsal is active"

WORK="$(mktemp -d /root/samithai-independent-restore.XXXXXXXX)"
chmod 700 "$WORK"
echo "========== DECRYPT INTO PRIVATE TEMPORARY DIRECTORY =========="
IFS= read -r -s -p 'Encrypted backup passphrase: ' PASS </dev/tty
printf '\n' >/dev/tty
exec 3<<<"$PASS"
unset PASS
gpg --batch --quiet --pinentry-mode loopback --no-symkey-cache \
    --passphrase-fd 3 --decrypt --output "$WORK/backup.tar" "$ARCHIVE" ||
    stop "Encrypted backup could not be authenticated/decrypted"
exec 3<&-

# Do not extract the .env or restore the original DB settings.
python3 - "$WORK/backup.tar" "$WORK" "$ARCHIVE_VERSION" <<'PY'
import hashlib,json,os,sys,tarfile
from pathlib import Path
archive,work=Path(sys.argv[1]),Path(sys.argv[2])
allowed={'mysql.sql.gz','application-state.tar.gz',
         'inventory-before.json','inventory-after.json','backup-metadata.json','SHA256SUMS'}
with tarfile.open(archive,'r:') as tar:
    members=tar.getmembers()
    file_members=[m for m in members if m.isfile()]
    names=[m.name.split('/') for m in file_members]
    if not names or any(
        len(x)!=2 or not x[0].startswith('samithai-recovery-snapshot.')
        or x[1] not in allowed or '..' in x for x in names
    ) or any(m.issym() or m.islnk() or m.isdev() for m in members):
        raise SystemExit('BLOCKED=UNEXPECTED_BACKUP_TAR_MEMBERS')
    present={x[1] for x in names}
    if present!=allowed:
        raise SystemExit('BLOCKED=BACKUP_FILES_INCOMPLETE')
    for m in file_members:
        out=work/m.name.split('/')[1]
        src=tar.extractfile(m)
        if src is None: raise SystemExit('BLOCKED=BACKUP_MEMBER_UNREADABLE')
        fd=os.open(out,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as writer:
            while block:=src.read(1024*1024): writer.write(block)
manifest={}
for line in (work/'SHA256SUMS').read_text().splitlines():
    h,name=line.split(None,1)
    name=name.lstrip('*')
    if name not in allowed-{'SHA256SUMS'} or len(h)!=64:
        raise SystemExit('BLOCKED=UNSAFE_MANIFEST')
    manifest[name]=h
if set(manifest)!=allowed-{'SHA256SUMS'}:
    raise SystemExit('BLOCKED=INCOMPLETE_MANIFEST')
for name,h in manifest.items():
    digestor=hashlib.sha256()
    with open(work/name,'rb') as fh:
        while block:=fh.read(1024*1024):
            digestor.update(block)
    digest=digestor.hexdigest()
    if digest!=h: raise SystemExit('BLOCKED=SNAPSHOT_CHECKSUM_MISMATCH')
a=json.loads((work/'inventory-before.json').read_text())
b=json.loads((work/'inventory-after.json').read_text())
meta=json.loads((work/'backup-metadata.json').read_text())
v=sys.argv[3]
required={'v2':13,'v3':14,'v4':15}
if (meta.get('format')!='samithai/versioned-backup/v1'
    or meta.get('inventory_version')!=v
    or meta.get('independent_restore_verified') is not False
    or a!=b
    or a.get('format')!='samith-recovery-inventory/'+v
    or a.get('database_driver')!='mysql'
    or len(a.get('tables',{}))!=required[v]
    or not all(row.get('present') is True for row in a.get('tables',{}).values())):
    raise SystemExit('BLOCKED=SOURCE_RECOVERY_INVENTORY_MISMATCH')
if a.get('media',{}).get('missing')!=0 or a.get('media',{}).get('checksum_mismatch')!=0:
    raise SystemExit('BLOCKED=SOURCE_MEDIA_INTEGRITY_FAILED')
print('DECRYPTED_SNAPSHOT_AND_MANIFEST=VERIFIED')
print('SOURCE_INVENTORY_BEFORE_AFTER=MATCHED')
PY

gzip -t "$WORK/mysql.sql.gz" || stop "Compressed MySQL dump corrupt"

# A mysqldump taken without --databases must not control schema selection.
python3 - "$WORK/mysql.sql.gz" <<'PY'
import gzip,re,sys
pattern=re.compile(r'^\s*(?:(?:CREATE|DROP|ALTER)\s+DATABASE\b|USE\s+)',re.I)
with gzip.open(sys.argv[1],'rt',encoding='utf-8',errors='replace') as fh:
    for line in fh:
        if pattern.search(line):
            raise SystemExit('BLOCKED=SQL_DUMP_ATTEMPTS_SCHEMA_SELECTION')
print('SQL_DUMP_SCHEMA_SELECTION=REJECTED')
PY

QA_DB="samithai_pr6_restore_$(date -u +%Y%m%d_%H%M%S)_$(python3 -c 'import secrets;print(secrets.token_hex(3))')"
[[ "$QA_DB" =~ ^[a-z0-9_]+$ && "$QA_DB" != *production* ]] ||
  stop "Unexpected generated QA database name"
"$MYSQL" --defaults-extra-file="$QACNF" --execute="CREATE DATABASE \`$QA_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" ||
  stop "Could not create independent QA database"
echo "RESTORED_ISOLATED_QA_DATABASE=$QA_DB"
echo "RESTORED_QA_SERVER_UUID_DISTINCT=YES"

gzip -dc "$WORK/mysql.sql.gz" |
  "$MYSQL" --defaults-extra-file="$QACNF" --binary-mode=1 "$QA_DB" ||
  stop "Independent QA MySQL import failed; preserve QA database for restricted diagnosis"

"$MYSQL" --defaults-extra-file="$QACNF" --batch --raw --skip-column-names "$QA_DB" \
    --execute="SELECT file_path, COALESCE(checksum,'') FROM media_assets WHERE file_path IS NOT NULL" \
    > "$WORK/media-references.tsv" ||
    stop "Unable to enumerate restored QA media records"

python3 - "$WORK/application-state.tar.gz" "$WORK/media-references.tsv" <<'PY'
import hashlib,sys,tarfile
from pathlib import PurePosixPath
ref={}
for line in open(sys.argv[2],encoding='utf-8'):
    path,sha=line.rstrip('\n').split('\t')
    if (not path.startswith('media/') or
        any(c in ('.','..') for c in PurePosixPath(path).parts) or
        path in ref):
        raise SystemExit('BLOCKED=RESTORED_MEDIA_PATH_INVALID')
    ref['storage/app/private/'+path]=sha.lower()
seen=set()
with tarfile.open(sys.argv[1],'r:gz') as tar:
    for member in tar:
        if member.issym() or member.islnk() or member.isdev():
            raise SystemExit('BLOCKED=PRIVATE_ARCHIVE_LINK_OR_DEVICE')
        if member.name not in ref: continue
        if not member.isfile(): raise SystemExit('BLOCKED=RESTORED_MEDIA_NOT_FILE')
        src=tar.extractfile(member)
        if src is None: raise SystemExit('BLOCKED=PRIVATE_MEDIA_FILE_UNREADABLE')
        h=hashlib.sha256()
        while data:=src.read(1024*1024): h.update(data)
        if ref[member.name] and ref[member.name]!=h.hexdigest():
            raise SystemExit('BLOCKED=RESTORED_MEDIA_HASH_MISMATCH')
        seen.add(member.name)
if seen!=set(ref): raise SystemExit('BLOCKED=RESTORED_MEDIA_MISSING')
print(f'INDEPENDENT_RESTORED_MEDIA_CHECKSUMS=PASS:{len(seen)}')
PY

"$MYSQL" --defaults-extra-file="$QACNF" --batch --skip-column-names "$QA_DB" \
   --execute="SELECT TABLE_NAME,TABLE_ROWS FROM information_schema.tables WHERE table_schema=DATABASE()" \
   > "$WORK/qa-table-inventory.txt"

python3 - "$WORK/inventory-before.json" "$WORK/qa-table-inventory.txt" <<'PY'
import json,re,sys
expected=json.load(open(sys.argv[1]))['tables']
# TABLE_ROWS is estimated for InnoDB; exact row counts are checked in SQL below.
seen={x.split('\t')[0] for x in open(sys.argv[2])}
required={k for k,v in expected.items() if v.get('present')}
if required-seen:
    raise SystemExit('BLOCKED=RESTORED_TABLES_MISSING')
print('INDEPENDENT_RESTORED_TABLES_PRESENT=PASS')
PY

# Compare exact row counts, never information_schema InnoDB estimates.
python3 - "$WORK/inventory-before.json" "$WORK/counts.sql" <<'PY'
import json,re,sys
snapshot=json.load(open(sys.argv[1]))
items=snapshot.get('tables',{})
sql=[]
for name,details in sorted(items.items()):
    if not re.fullmatch(r'[a-z][a-z0-9_]{0,63}',name):
        raise SystemExit('BLOCKED=UNSAFE_BACKUP_TABLE_IDENTIFIER')
    if details.get('present') is True:
        sql.append(f"SELECT '{name}' AS tbl, COUNT(*) AS rows FROM `{name}`")
if not sql:
    raise SystemExit('BLOCKED=NO_EXPECTED_TABLES')
with open(sys.argv[2],'w') as fh:
    fh.write(" UNION ALL ".join(sql)+";\n")
PY
"$MYSQL" --defaults-extra-file="$QACNF" --batch --skip-column-names     "$QA_DB" < "$WORK/counts.sql" > "$WORK/qa-exact-counts.tsv" ||
    stop "Exact QA row count query failed"
python3 - "$WORK/inventory-before.json" "$WORK/qa-exact-counts.tsv" <<'PY'
import json,sys
expected=json.load(open(sys.argv[1]))['tables']
actual={}
for line in open(sys.argv[2]):
    name,rows=line.rstrip('\n').split('\t')
    actual[name]=int(rows)
for name,data in expected.items():
    if data.get('present') is True and data.get('count')!=actual.get(name):
        raise SystemExit('BLOCKED=QA_ROW_COUNT_MISMATCH:'+name)
print('INDEPENDENT_RESTORED_EXACT_ROW_COUNTS=PASS')
PY

echo "INDEPENDENT_MYSQL_IMPORT=SUCCESS"
echo "RESTORED_BACKUP_INVENTORY_VERSION=$ARCHIVE_VERSION"
echo "ENCRYPTED_BACKUP_VALIDATION=PASS"
echo "REAL_PRIVATE_MEDIA_CHECKSUMS=PASS"
echo "QA_RESTORED_DATABASE=$QA_DB"
echo "QA_RESTORED_DATABASE_CONTAINS_REAL_PRIVATE_DATA=YES"
echo "QA_DATABASE_MUST_REMAIN_ISOLATED_AND_BE_REMOVED_BY_AUTHORIZED_OPERATOR"
echo "TEMPORARY_DECRYPTED_FILES=DESTROYED_ON_SCRIPT_EXIT"
echo "PRODUCTION_MYSQL_AND_MEDIA=UNCHANGED"
