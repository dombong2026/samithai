#!/usr/bin/env bash
# Samith AI: backup-first, intentional import of 30 editorial drafts.
set +x
set -Eeuo pipefail
umask 077

if [[ $# -ne 1 || ( "$1" != "--check" && "$1" != "--apply" ) ]]; then
  echo "Usage: bash import-starter30-drafts.sh --check|--apply" >&2; exit 2
fi
MODE="$1"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
PHP="/www/server/php/85/bin/php"
COMMAND="samithai:import-starter-drafts"
echo "========== STARTER 30 CONTENT IMPORT PREFLIGHT =========="
test "$(id -u)" -eq 0
for bin in git runuser gzip flock; do command -v "$bin" >/dev/null; done
test -x "$PHP"
test -f "$APP/artisan"
test -f "$APP/vendor/autoload.php"
test -f "$APP/.env"
test -f "$APP/storage/app/installed.lock"
test -f "$APP/database/content/starter-2026-11/01-marketing-social-media.json"
test -f "$APP/app/Console/Commands/ImportStarterPromptDrafts.php"
test "$(git hash-object "$APP/app/Console/Commands/ImportStarterPromptDrafts.php")" = \
  "4af143668da25c2b02b235698e5c55fb913e0fa0" || {
  echo "BLOCKED=UNVERIFIED_IMPORTER"; exit 1
}
id www >/dev/null
if [[ -e "$APP/storage/framework/down" || -e "$APP/storage/framework/maintenance.php" ]]; then
  echo "BLOCKED=MAINTENANCE_MODE"; exit 1
fi
if [[ ! -c /dev/tty ]]; then echo "BLOCKED=INTERACTIVE_TTY_REQUIRED"; exit 1; fi
printf 'Enter existing Admin/Creator email to own these 30 drafts: ' > /dev/tty
IFS= read -r AUTHOR_EMAIL < /dev/tty
case "$AUTHOR_EMAIL" in
 *@*.*) : ;;
 *) echo "BLOCKED=INVALID_AUTHOR_EMAIL"; exit 1 ;;
esac

echo "========== CHECK BUNDLE, AUTHOR AND EXISTING SLUGS =========="
(
 cd "$APP"
 runuser -u www -- "$PHP" artisan "$COMMAND" --check --author-email="$AUTHOR_EMAIL" --no-ansi
)
echo "DRAFT_IMPORT_PREFLIGHT=PASSED"
if [[ "$MODE" == "--check" ]]; then
  echo "DATABASE_AND_STOREFRONT=UNCHANGED"
  exit 0
fi

exec 9>/root/samithai-starter30-import.lock
flock -n 9 || { echo "BLOCKED=CONCURRENT_IMPORT"; exit 1; }
if [[ -x /www/server/mysql/bin/mysqldump ]]; then
  MYSQLDUMP="/www/server/mysql/bin/mysqldump"
else
  MYSQLDUMP="$(command -v mysqldump || true)"
fi
test -n "$MYSQLDUMP" && test -x "$MYSQLDUMP" || { echo "BLOCKED=MYSQLDUMP_REQUIRED"; exit 1; }
BACKUP="$(mktemp -d /root/samithai-starter30-mysql-backup.XXXXXXXX)"
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
 foreach (["users","categories","prompts"] as $table) {
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
echo "========== VERIFY ALL 30 DRAFTS REMAIN UNPUBLISHED =========="
POST="$(cd "$APP" && runuser -u www -- "$PHP" artisan "$COMMAND" --check --author-email="$AUTHOR_EMAIL" --no-ansi)"
printf '%s\n' "$POST" | grep -Fxq 'DRAFTS_ALREADY_MATCHING=30'
printf '%s\n' "$POST" | grep -Fxq 'DRAFTS_TO_CREATE=0'
printf '%s\n' "$POST" | grep -Fxq 'READ_ONLY_PREFLIGHT=PASSED'
echo "STARTER30_DRAFT_IMPORT=SUCCESS"
echo "DRAFTS_PRESENT=30"
echo "PUBLIC_PROMPTS_PUBLISHED_BY_IMPORT=0"
echo "DATABASE_MIGRATIONS=NONE"
echo "PAYMENT_AND_LICENSING=UNCHANGED"
echo "MYSQL_BACKUP_PATH=$BACKUP/mysql-before-import.sql.gz"
echo "NEXT=Review all drafts in Dashboard > Prompt Manager; do not publish without human QA"
