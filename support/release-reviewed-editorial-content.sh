#!/usr/bin/env bash
# Controlled three-stage public content release; --check is read-only.
set +x
set -Eeuo pipefail
umask 077
if [[ $# -ne 1 || ! "$1" =~ ^(--check|--pages|--first|--second)$ ]]; then
  echo 'Usage: release-reviewed-editorial-content.sh --check|--pages|--first|--second' >&2
  exit 2
fi
MODE="$1"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
PHP="/www/server/php/85/bin/php"
VHOST="/www/server/panel/vhost/nginx/samithai.com.conf"
COMMAND="samithai:publish-reviewed-content"
COMMAND_BLOB="a89edc27808076dc313ead0d50a98b5981134b4b"
echo "========== EDITORIAL RELEASE SAFETY PREFLIGHT =========="
test "$(id -u)" -eq 0
for bin in git runuser gzip flock curl; do command -v "$bin" >/dev/null; done
test -x "$PHP"
test -f "$APP/artisan" && test -f "$APP/vendor/autoload.php"
test -f "$APP/.env" && test -f "$APP/storage/app/installed.lock"
test -f "$VHOST"
grep -Fq "root $APP/public;" "$VHOST" || { echo 'BLOCKED=NGINX_ROOT_MISMATCH'; exit 1; }
test -f "$APP/app/Console/Commands/PublishReviewedCoreContent.php"
test ! -L "$APP/app/Console/Commands/PublishReviewedCoreContent.php"
test "$(git hash-object "$APP/app/Console/Commands/PublishReviewedCoreContent.php")" = "$COMMAND_BLOB" || {
  echo 'BLOCKED=UNVERIFIED_PUBLICATION_COMMAND'; exit 1
}
while IFS='|' read -r path target; do
  test -f "$APP/$path" && test ! -L "$APP/$path"
  test "$(git hash-object "$APP/$path")" = "$target" || { echo "BLOCKED=CONTENT_DRIFT:$path"; exit 1; }
done <<'SOURCE_HASHES'
database/content/essential-pages-2026-11/01-platform-and-creators.json|4a607ee9c3f3638c768eb650160f3ca5d841467f
database/content/essential-pages-2026-11/02-trust-and-guidance.json|39f7a1c0c018aed8b6dad376843c431fb8af77c8
database/content/educational-blog-2026-11/01-fundamentals.json|37ddd2a420b3e9f068c6d7df84b7e58b99b47ec8
SOURCE_HASHES
if [[ -e "$APP/storage/framework/down" || -e "$APP/storage/framework/maintenance.php" ]]; then
  echo "BLOCKED=APP_IN_MAINTENANCE"; exit 1
fi
id www >/dev/null
test -c /dev/tty || { echo 'BLOCKED=INTERACTIVE_TTY_REQUIRED'; exit 1; }
printf 'Existing administrator email: ' >/dev/tty
IFS= read -r ADMIN_EMAIL </dev/tty
case "$ADMIN_EMAIL" in *@*.*) : ;; *) echo 'BLOCKED=INVALID_EMAIL'; exit 1 ;; esac
echo "========== READ-ONLY CONTENT AND DEPENDENCY AUDIT =========="
(
 cd "$APP"
 runuser -u www -- "$PHP" artisan "$COMMAND" --check --reviewer-email="$ADMIN_EMAIL" --no-ansi
)
echo "EDITORIAL_RELEASE_PREFLIGHT=PASSED"
if [[ "$MODE" == "--check" ]]; then
  echo "DATABASE_AND_PUBLICATION=UNCHANGED"
  exit 0
fi

case "$MODE" in
 --pages)
   STAGE=publish-pages
   CONFIRM=PUBLISH-SIX-REVIEWED-PAGES
   LABEL='ALL SIX INFORMATIONAL PAGES'
   URLS='/pages/about-samith-ai /pages/how-it-works /pages/become-a-creator /pages/prompt-quality-standards /pages/ai-tools-and-compatibility /pages/responsible-ai-usage'
   ;;
 --first)
   STAGE=publish-first-article
   CONFIRM=PUBLISH-FIRST-REVIEWED-ARTICLE
   LABEL='ARTICLE 1: WHAT IS AN AI PROMPT?'
   URLS='/blog/what-is-an-ai-prompt-beginners-guide'
   ;;
 --second)
   STAGE=publish-second-article
   CONFIRM=PUBLISH-SECOND-REVIEWED-ARTICLE
   LABEL='ARTICLE 2: HOW TO WRITE BETTER PROMPTS'
   URLS='/blog/how-to-write-better-prompts-for-ai-assistants'
   ;;
esac
printf '\nPublishing %s requires your independent preview and sign-off.\n' "$LABEL" >/dev/tty
printf 'Check facts, rights, links, privacy, legal wording, formatting, and mobile display.\n' >/dev/tty
printf 'Publication is public and can be indexed. Type %s to confirm: ' "$CONFIRM" >/dev/tty
IFS= read -r APPROVAL </dev/tty
if [[ "$APPROVAL" != "$CONFIRM" ]]; then
  echo "BLOCKED=EDITORIAL_SIGNOFF_NOT_ATTESTED"; exit 1
fi

exec 9>/root/samithai-editorial-publish.lock
flock -n 9 || { echo 'BLOCKED=CONCURRENT_PUBLISH'; exit 1; }
DUMP="/www/server/mysql/bin/mysqldump"
if [[ ! -x "$DUMP" ]]; then DUMP="$(command -v mysqldump || true)"; fi
test -n "$DUMP" && test -x "$DUMP" || { echo 'BLOCKED=MYSQLDUMP_REQUIRED'; exit 1; }
BACKUP="$(mktemp -d /root/samithai-editorial-publish-backup.XXXXXXXX)"
trap 'rm -f -- "$BACKUP/mysql-client.cnf"' EXIT
echo "========== VERIFY MYSQL DATABASE AND CREATE BACKUP =========="
(
 cd "$APP"
 MYSQL_CNF_FILE="$BACKUP/mysql-client.cnf" MYSQL_NAME_FILE="$BACKUP/database-name" \
 "$PHP" -r '
 require "vendor/autoload.php";
 $app=require "bootstrap/app.php";
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 if (config("database.default")!=="mysql") {
  fwrite(STDERR,"BLOCKED=NON_MYSQL\n");exit(1);
 }
 foreach(["users","content_entries","categories","audit_events"] as $name) {
  if (!\Illuminate\Support\Facades\Schema::hasTable($name)) {
   fwrite(STDERR,"BLOCKED=MISSING_REQUIRED_TABLE\n");exit(1);
  }
 }
 $c=config("database.connections.mysql");
 $db=(string)($c["database"]??"");
 if(!preg_match("/^[A-Za-z0-9_]+$/D",$db)){fwrite(STDERR,"BLOCKED=INVALID_DB\n");exit(1);}
 $q=static fn($v)=>"\"".str_replace(["\\","\"","\n","\r"],["\\\\","\\\"","",""],(string)$v)."\"";
 $o=["[client]","user=".$q($c["username"]??""),"password=".$q($c["password"]??"")];
 if(!empty($c["unix_socket"])) $o[]="socket=".$q($c["unix_socket"]);
 else {$o[]="host=".$q($c["host"]??"127.0.0.1");$o[]="port=".((int)($c["port"]??3306));}
 if(file_put_contents(getenv("MYSQL_CNF_FILE"),implode("\n",$o)."\n",LOCK_EX)===false
 || file_put_contents(getenv("MYSQL_NAME_FILE"),$db,LOCK_EX)===false) exit(1);
 chmod(getenv("MYSQL_CNF_FILE"),0600);chmod(getenv("MYSQL_NAME_FILE"),0600);
 '
)
DB="$(cat "$BACKUP/database-name")"
"$DUMP" --defaults-extra-file="$BACKUP/mysql-client.cnf" \
  --single-transaction --quick --no-tablespaces --default-character-set=utf8mb4 \
  "$DB" | gzip -1 > "$BACKUP/mysql-pre-stage.sql.gz"
gzip -t "$BACKUP/mysql-pre-stage.sql.gz"
test -s "$BACKUP/mysql-pre-stage.sql.gz"
rm -f -- "$BACKUP/mysql-client.cnf"
echo 'MYSQL_BACKUP=VERIFIED'
echo "MYSQL_BACKUP_PATH=$BACKUP/mysql-pre-stage.sql.gz"

echo "========== EXECUTE EXPLICIT EDITORIAL PUBLICATION =========="
(
 cd "$APP"
 runuser -u www -- "$PHP" artisan "$COMMAND" "--$STAGE" \
   --reviewer-email="$ADMIN_EMAIL" --confirm="$CONFIRM" --no-ansi
)
echo "========== VERIFY DATABASE RELEASE STATUS =========="
(
 cd "$APP"
 runuser -u www -- "$PHP" artisan "$COMMAND" --check --reviewer-email="$ADMIN_EMAIL" --no-ansi
)
for path in $URLS; do
 HTTP_CODE="$(curl --noproxy '*' --fail --silent --show-error --insecure \
  --retry 2 --connect-timeout 8 --max-time 25 \
  --resolve "samithai.com:443:127.0.0.1" \
  "https://samithai.com$path" --output /dev/null --write-out '%{http_code}')"
 if [[ "$HTTP_CODE" != "200" ]]; then
  echo "BLOCKED=PUBLIC_URL_NOT_HTTP_200:$path:$HTTP_CODE"
  exit 1
 fi
 printf 'PUBLIC_URL=HTTP_200:%s\n' "$path"
done
echo "EDITORIAL_STAGE_PUBLICATION_AND_HTTP=VERIFIED"
echo "EDITORIAL_STAGE=$STAGE"
echo "OTHER_SIX_BLOG_ARTICLES=UNCHANGED_DRAFTS"
echo "BACKUP_PATH=$BACKUP/mysql-pre-stage.sql.gz"
