#!/usr/bin/env bash
# Samith AI: backup-first, explicit import of six editorial page drafts.
set +x
set -Eeuo pipefail
umask 077

if [[ $# -ne 1 || ( "$1" != "--check" && "$1" != "--apply" ) ]]; then
  echo "Usage: bash import-essential-page-drafts.sh --check|--apply" >&2; exit 2
fi
MODE="$1"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
PHP="/www/server/php/85/bin/php"
COMMAND="samithai:import-essential-pages"
echo "========== ESSENTIAL PAGE DRAFT IMPORT PREFLIGHT =========="
test "$(id -u)" -eq 0
for bin in git runuser gzip flock; do command -v "$bin" >/dev/null; done
test -x "$PHP"
test -f "$APP/artisan"
test -f "$APP/vendor/autoload.php"
test -f "$APP/.env"
test -f "$APP/storage/app/installed.lock"
test -f "$APP/database/content/essential-pages-2026-11/01-platform-and-creators.json"
test -f "$APP/app/Console/Commands/ImportEssentialPageDrafts.php"
test "$(git hash-object "$APP/app/Console/Commands/ImportEssentialPageDrafts.php")" = \
  "264c7461ac706c82adb2664d0bb2f850c2b3a061" || {
  echo "BLOCKED=UNVERIFIED_IMPORTER"; exit 1
}
id www >/dev/null
if [[ -e "$APP/storage/framework/down" || -e "$APP/storage/framework/maintenance.php" ]]; then
  echo "BLOCKED=MAINTENANCE_MODE"; exit 1
fi
if [[ ! -c /dev/tty ]]; then echo "BLOCKED=INTERACTIVE_TTY_REQUIRED"; exit 1; fi
printf 'Enter existing administrator email to own six page drafts: ' > /dev/tty
IFS= read -r AUTHOR_EMAIL < /dev/tty
case "$AUTHOR_EMAIL" in
 *@*.*) : ;;
 *) echo "BLOCKED=INVALID_AUTHOR_EMAIL"; exit 1 ;;
esac

echo "========== CHECK ALL SIX PAGES, ADMIN OWNERSHIP AND SLUGS =========="
(
 cd "$APP"
 runuser -u www -- "$PHP" artisan "$COMMAND" --check --author-email="$AUTHOR_EMAIL" --no-ansi
)
echo "ESSENTIAL_PAGE_IMPORT_PREFLIGHT=PASSED"
if [[ "$MODE" == "--check" ]]; then
  echo "DATABASE_AND_STOREFRONT=UNCHANGED"
  exit 0
fi

exec 9>/root/samithai-essential-pages-import.lock
flock -n 9 || { echo "BLOCKED=CONCURRENT_IMPORT"; exit 1; }
if [[ -x /www/server/mysql/bin/mysqldump ]]; then
  MYSQLDUMP="/www/server/mysql/bin/mysqldump"
else
  MYSQLDUMP="$(command -v mysqldump || true)"
fi
test -n "$MYSQLDUMP" && test -x "$MYSQLDUMP" || { echo "BLOCKED=MYSQLDUMP_REQUIRED"; exit 1; }
BACKUP="$(mktemp -d /root/samithai-essential-pages-mysql-backup.XXXXXXXX)"
trap 'rm -f -- "$BACKUP/mysql-client.cnf"' EXIT

echo "========== VERIFY AND BACK UP MYSQL BEFORE WRITING =========="
(
 cd "$APP"
 MYSQL_CNF_FILE="$BACKUP/mysql-client.cnf" MYSQL_NAME_FILE="$BACKUP/database-name" \
 "$PHP" -r '
 require "vendor/autoload.php";
 $app=require "bootstrap/app.php";
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 if (config("database.default") !== "mysql") {
   fwrite(STDERR,"BLOCKED=NON_MYSQL_DATABASE\n"); exit(1);
 }
 foreach (["users","content_entries"] as $table) {
   if (!\Illuminate\Support\Facades\Schema::hasTable($table)) {
     fwrite(STDERR,"BLOCKED=MISSING_CORE_TABLE\n"); exit(1);
   }
 }
 $c=config("database.connections.mysql");
 $db=(string)($c["database"] ?? "");
 if (!preg_match("/^[A-Za-z0-9_]+$/D",$db)) {
   fwrite(STDERR,"BLOCKED=INVALID_DATABASE_NAME\n"); exit(1);
 }
 $quote=static fn($v) => "\"".str_replace(
   ["\\","\"","\n","\r"],["\\\\","\\\"","",""],(string)$v
 )."\"";
 $lines=["[client]","user=".$quote($c["username"] ?? ""),"password=".$quote($c["password"] ?? "")];
 if (!empty($c["unix_socket"])) $lines[]="socket=".$quote($c["unix_socket"]);
 else {
   $lines[]="host=".$quote($c["host"] ?? "127.0.0.1");
   $lines[]="port=".((int)($c["port"] ?? 3306));
 }
 if (file_put_contents(getenv("MYSQL_CNF_FILE"),implode("\n",$lines)."\n",LOCK_EX)===false
     || file_put_contents(getenv("MYSQL_NAME_FILE"),$db,LOCK_EX)===false) exit(1);
 chmod(getenv("MYSQL_CNF_FILE"),0600);
 chmod(getenv("MYSQL_NAME_FILE"),0600);
 '
)
DB_NAME="$(cat "$BACKUP/database-name")"
"$MYSQLDUMP" --defaults-extra-file="$BACKUP/mysql-client.cnf" \
  --single-transaction --quick --no-tablespaces --default-character-set=utf8mb4 \
  "$DB_NAME" | gzip -1 > "$BACKUP/mysql-before-import.sql.gz"
gzip -t "$BACKUP/mysql-before-import.sql.gz"
test -s "$BACKUP/mysql-before-import.sql.gz"
rm -f -- "$BACKUP/mysql-client.cnf"
echo "MYSQL_BACKUP=VERIFIED"
echo "MYSQL_BACKUP_PATH=$BACKUP/mysql-before-import.sql.gz"

echo "========== ATOMIC DRAFT-ONLY DATABASE IMPORT =========="
(
 cd "$APP"
 runuser -u www -- "$PHP" artisan "$COMMAND" --apply --author-email="$AUTHOR_EMAIL" --no-ansi
)
echo "========== VERIFY ALL SIX PAGES REMAIN DRAFTS =========="
POST="$(cd "$APP" && runuser -u www -- "$PHP" artisan "$COMMAND" --check --author-email="$AUTHOR_EMAIL" --no-ansi)"
printf '%s\n' "$POST" | grep -Fxq 'EXISTING_MATCHING_DRAFTS=6'
printf '%s\n' "$POST" | grep -Fxq 'NEW_DRAFTS_TO_CREATE=0'
printf '%s\n' "$POST" | grep -Fxq 'READ_ONLY_PREFLIGHT=PASSED'
echo "ESSENTIAL_PAGE_DATABASE_IMPORT=SUCCESS"
echo "DRAFT_PAGES_PRESENT=6"
echo "PUBLIC_PAGES_PUBLISHED_BY_IMPORT=0"
echo "DATABASE_MIGRATIONS=NONE"
echo "PAYMENTS_LICENSING_AND_FRONTEND_LAYOUTS=UNCHANGED"
echo "MYSQL_BACKUP_PATH=$BACKUP/mysql-before-import.sql.gz"
echo "NEXT=Review six draft pages in Dashboard > Page Manager and publish individually after factual/legal review"
