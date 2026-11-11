#!/usr/bin/env bash
# Samith AI: Prompt Quality Center — guarded aaPanel upgrade.
# New schema: one additive prompt_quality_reviews table.
# No existing table changes, payment changes, .env or nginx changes.
set +x
set -Eeuo pipefail
umask 077

if [[ $# -ne 1 || ( "$1" != "--check" && "$1" != "--apply" ) ]]; then
  echo "Usage: bash deploy-prompt-quality-center.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
SOURCE="1405ed3cd8089408e13a47b10274884bfd29353b"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
PHP="/www/server/php/85/bin/php"
DOMAIN="samithai.com"
VHOST="/www/server/panel/vhost/nginx/$DOMAIN.conf"
MIGRATION="2026_11_11_000100_create_prompt_quality_reviews"
MIGRATION_FILE="database/migrations/$MIGRATION.php"
FILES="$(cat <<'FILEHASHES'
app/Http/Controllers/Dashboard/PromptController.php|84d66603f9f0bf5f40db9703810352724b82b920|01059f38a5f005d924c0e7917609c8cea4b151d5
app/Http/Controllers/PromptQualityController.php|-|ec2ab26a5a78a03f7bc1d109d3a5d9b8b6312c80
app/Models/Prompt.php|16760f0a226df9f19e16cb08dbc8747afc4ae9d0|592320e4c53b7451f5cfa475706e12aef695bed9
app/Models/PromptQualityReview.php|-|d92b7832bc7a92e1f6243b0db595fdfc5db1cb4d
app/Support/FrontendPageRegistry.php|c35b3eaca525b0f13ad9840a9b83cfea5eb986d8|5ab98c2a26e250028fe25c2f3ebbc50a87aa1581
app/Support/PromptQualityGate.php|-|b39e4685cb244bf895e2aeaa448c91f79d9de0a0
app/Support/StarterPromptScenarios.php|-|e128243f99f8aeae5dea57149c4dd7c54050e04f
app/Support/WorkspaceLaunchpad.php|7c004bd19cdeb31fa3c46fef3a46497b059a4c59|509639316d8bf6769c75e1741fc844f378421278
database/migrations/2026_11_11_000100_create_prompt_quality_reviews.php|-|017977466021680019e6c4f53973c3bd225139c0
public/assets/app.css|332da0a19a0c1bc3ad75d0d552299ed1b996841c|b6653a84ca1b85357a503e14e9f34aae9a29e0d3
resources/views/dashboard/prompt-quality-index.blade.php|-|b7fca5e8224c1f5fad4684a0cb77ac1171140567
resources/views/dashboard/prompt-quality.blade.php|-|b47ece74f3a73101ad5002cb832cd0679d47b02f
resources/views/dashboard/prompts.blade.php|8b6b28dfcab1abcfa919832077f50bb885692dc4|f68a765784c580828f84ced54635c59354819d50
resources/views/layouts/workspace.blade.php|3ddf90b32674d5cb1fb2ff3fd7f069d07ad41191|6568aa3d13b9150c0e52c29b4e27adf87b59c9d0
resources/views/partials/workspace-search.blade.php|52b4a5d95760a5cca9c4f99d86670cbdb5bbb501|ee456a35838df10a42d54f68379c4f5ec51e97c2
routes/web.php|b6dc90613caaefdd064100a5d336a3eb36c3be44|bf1b7751b69923dab677903b4a904b11f0d9c9ab
FILEHASHES
)"

echo "========== SAMITH AI PROMPT QUALITY CENTER PREFLIGHT =========="
test "$(id -u)" -eq 0
for cmd in curl git python3 flock runuser gzip; do command -v "$cmd" >/dev/null; done
test -x "$PHP"
test -f "$APP/artisan"
test -f "$APP/vendor/autoload.php"
test -f "$APP/.env"
test -f "$APP/storage/app/installed.lock"
test -f "$VHOST"
id www >/dev/null
grep -Fq "root $APP/public;" "$VHOST" || { echo "BLOCKED=NGINX_ROOT_MISMATCH"; exit 1; }
if [[ -e "$APP/storage/framework/down" || -e "$APP/storage/framework/maintenance.php" ]]; then
  echo "BLOCKED=MAINTENANCE_ALREADY_ACTIVE"; exit 1
fi
if [[ -x /www/server/mysql/bin/mysqldump ]]; then
  MYSQLDUMP=/www/server/mysql/bin/mysqldump
else
  MYSQLDUMP="$(command -v mysqldump || true)"
fi
test -n "$MYSQLDUMP" && test -x "$MYSQLDUMP" || { echo "BLOCKED=MYSQLDUMP_REQUIRED"; exit 1; }

# The existing site has resources/views/, but the new workspace/ child is
# absent on first deployment. Block symlinks/non-directories before modifying
# application files. The new directory is created after the MySQL backup.
WORKSPACE_VIEW_DIR="$APP/resources/views/workspace"
test -d "$APP/resources/views" && test ! -L "$APP/resources/views" || {
  echo "BLOCKED=WORKSPACE_VIEWS_PARENT_UNSAFE"; exit 1;
}
if [[ -L "$WORKSPACE_VIEW_DIR" || ( -e "$WORKSPACE_VIEW_DIR" && ! -d "$WORKSPACE_VIEW_DIR" ) ]]; then
  echo "BLOCKED=WORKSPACE_VIEW_DIRECTORY_UNSAFE"
  exit 1
fi
if [[ -d "$WORKSPACE_VIEW_DIR" ]]; then
  echo "WORKSPACE_VIEW_DIRECTORY=EXISTING"
else
  echo "WORKSPACE_VIEW_DIRECTORY=CREATION_REQUIRED"
fi

BEFORE=0
AFTER=0
while IFS='|' read -r path prior target; do
  file="$APP/$path"
  if [[ "$prior" == "-" && ! -e "$file" && ! -L "$file" ]]; then
    BEFORE=$((BEFORE+1))
  elif [[ -f "$file" && ! -L "$file" ]]; then
    actual="$(git hash-object "$file")"
    if [[ "$actual" == "$prior" ]]; then BEFORE=$((BEFORE+1))
    elif [[ "$actual" == "$target" ]]; then AFTER=$((AFTER+1))
    else echo "BLOCKED=CHANGED_PRODUCTION_FILE:$path"; echo "ACTUAL_SHA=$actual"; exit 1
    fi
  else
    echo "BLOCKED=UNEXPECTED_FILE_OR_SYMLINK:$path"; exit 1
  fi
done <<< "$FILES"
if [[ "$BEFORE" -eq 16 && "$AFTER" -eq 0 ]]; then SOURCE_STATE=PREVIOUS
elif [[ "$BEFORE" -eq 0 && "$AFTER" -eq 16 ]]; then SOURCE_STATE=ALREADY_UPDATED
else
  echo "BLOCKED=MIXED_PRODUCTION_FILES"
  echo "PREVIOUS=$BEFORE TARGET=$AFTER"
  exit 1
fi
echo "SOURCE_FILES=16_OF_16_VERIFIED"
echo "SOURCE_STATE=$SOURCE_STATE"

DATABASE_STATE="$(cd "$APP"; runuser -u www -- "$PHP" -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config("database.default") !== "mysql"
    || !\Illuminate\Support\Facades\Schema::hasTable("migrations")
    || !\Illuminate\Support\Facades\Schema::hasTable("users")
    || !\Illuminate\Support\Facades\Schema::hasTable("audit_events")
    || !\Illuminate\Support\Facades\Schema::hasTable("prompt_saves")
    || !\Illuminate\Support\Facades\Schema::hasTable("workspace_preferences")
    || !\Illuminate\Support\Facades\Schema::hasTable("workspace_tasks")
    || !\Illuminate\Support\Facades\Schema::hasTable("prompts")
    || !\Illuminate\Support\Facades\Schema::hasTable("prompt_reviews")) {
    fwrite(STDERR, "BLOCKED=UNEXPECTED_MYSQL_SCHEMA\n"); exit(1);
}
$table = \Illuminate\Support\Facades\Schema::hasTable("prompt_quality_reviews");
$migrated = \Illuminate\Support\Facades\DB::table("migrations")
  ->where("migration", "2026_11_11_000100_create_prompt_quality_reviews")->exists();
if ($table !== $migrated) {
    fwrite(STDERR, "BLOCKED=PARTIAL_PROMPT_QUALITY_MIGRATION\n"); exit(1);
}
if ($table) {
    foreach (["prompt_id", "reviewer_id", "result", "model_name", "test_scenario", "findings", "checks", "content_sha256"] as $col) {
        if (!\Illuminate\Support\Facades\Schema::hasColumn("prompt_quality_reviews", $col)) {
            fwrite(STDERR, "BLOCKED=PROMPT_QUALITY_SCHEMA_MISMATCH\n"); exit(1);
        }
    }
}
echo $table ? "READY" : "ABSENT";
')"
if [[ "$SOURCE_STATE" == ALREADY_UPDATED && "$DATABASE_STATE" != READY ]]; then
  echo "BLOCKED=CODE_PRESENT_BUT_MIGRATION_ABSENT"
  exit 1
fi
echo "DATABASE_MIGRATION_STATE=$DATABASE_STATE"

if [[ "$MODE" == "--check" ]]; then
  echo "PROMPT_QUALITY_PREFLIGHT=PASSED"
  echo "PRODUCTION_FILES_AND_DATABASE=UNCHANGED"
  exit 0
fi

exec 9>/root/samithai-prompt-quality.lock
flock -n 9 || { echo "BLOCKED=CONCURRENT_DEPLOYMENT"; exit 1; }
WORK="$(mktemp -d /root/samithai-prompt-quality-stage.XXXXXXXX)"
BACKUP=""
WRITTEN=0
MAINT=0
WORKSPACE_DIR_CREATED=0
finish() {
  code=$?
  trap - EXIT INT TERM
  set +e
  if [[ -n "$BACKUP" ]]; then rm -f -- "$BACKUP/mysql-client.cnf"; fi
  if [[ "$code" -ne 0 && "$WRITTEN" -eq 1 ]]; then
    echo "ROLLBACK=RESTORING_PREVIOUS_APPLICATION_FILES"
    if [[ "$MAINT" -eq 0 ]]; then
      if (cd "$APP"; runuser -u www -- "$PHP" artisan down --retry=60 --no-ansi) >/dev/null 2>&1; then MAINT=1; fi
    fi
    restored=1
    while IFS='|' read -r path prior target; do
      if [[ "$prior" == "-" ]]; then
        rm -f -- "$APP/$path" || restored=0
      else
        cp -p -- "$BACKUP/$path" "$APP/$path" || restored=0
      fi
    done <<< "$FILES"
    while IFS='|' read -r path prior target; do
      if [[ "$prior" == "-" ]]; then
        [[ ! -e "$APP/$path" && ! -L "$APP/$path" ]] || { echo "ROLLBACK_FILE_MISMATCH=$path"; restored=0; }
      else
        [[ -f "$APP/$path" && ! -L "$APP/$path" && "$(git hash-object "$APP/$path" 2>/dev/null)" == "$prior" ]] || {
          echo "ROLLBACK_FILE_MISMATCH=$path"; restored=0;
        }
      fi
    done <<< "$FILES"
    if [[ "$restored" -eq 1 ]]; then echo "APPLICATION_ROLLBACK=VERIFIED"
    else echo "APPLICATION_ROLLBACK=FAILED"; fi
    # Only remove a directory this run created, and only if now empty.
    if [[ "$WORKSPACE_DIR_CREATED" -eq 1 ]]; then
      if rmdir -- "$WORKSPACE_VIEW_DIR"; then
        echo "WORKSPACE_VIEW_DIRECTORY_ROLLBACK=REMOVED_EMPTY"
      else
        echo "WORKSPACE_VIEW_DIRECTORY_ROLLBACK=PRESERVED_NONEMPTY"
      fi
    fi
    if (
      cd "$APP"
      runuser -u www -- "$PHP" artisan route:clear --no-ansi
      runuser -u www -- "$PHP" artisan view:clear --no-ansi
      runuser -u www -- "$PHP" artisan view:cache --no-ansi
    ) >"$BACKUP/rollback-cache.log" 2>&1; then echo "ROLLBACK_CACHES=REBUILT"
    else echo "ROLLBACK_CACHES=FAILED"; echo "ROLLBACK_CACHE_LOG=$BACKUP/rollback-cache.log"; fi
    echo "DATABASE_RESTORE=NOT_AUTOMATIC_ADDITIVE_TABLE_ONLY"
    echo "MYSQL_BACKUP=$BACKUP/mysql-pre-upgrade.sql.gz"
  fi
  if [[ "$MAINT" -eq 1 ]]; then
    if (cd "$APP"; runuser -u www -- "$PHP" artisan up --no-ansi) >/dev/null 2>&1 \
      && [[ ! -e "$APP/storage/framework/down" && ! -e "$APP/storage/framework/maintenance.php" ]]; then
      echo "MAINTENANCE_EXIT=VERIFIED"
    else echo "MAINTENANCE_EXIT=FAILED"; fi
  fi
  rm -rf -- "$WORK"
  exit "$code"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ "$SOURCE_STATE" == PREVIOUS ]]; then
  echo "========== VERIFY PINNED GITHUB SOURCE =========="
  while IFS='|' read -r path prior target; do
    mkdir -p "$WORK/$(dirname "$path")"
    curl -fsSL --retry 3 --proto '=https' --connect-timeout 10 --max-time 60 \
      "https://raw.githubusercontent.com/dombong2026/samithai/$SOURCE/$path" -o "$WORK/$path"
    test "$(git hash-object "$WORK/$path")" = "$target" || {
      echo "BLOCKED=PINNED_BLOB_MISMATCH:$path"; exit 1;
    }
    if [[ "$path" == *.php && "$path" != *.blade.php ]]; then
      "$PHP" -l "$WORK/$path" >/dev/null
    fi
  done <<< "$FILES"
  echo "PINNED_SOURCE_BLOBS_AND_PHP_SYNTAX=VERIFIED"

  BACKUP="$(mktemp -d /root/samithai-prompt-quality-backup.XXXXXXXX)"
  while IFS='|' read -r path prior target; do
    if [[ "$prior" != "-" ]]; then
      mkdir -p "$BACKUP/$(dirname "$path")"
      cp -p -- "$APP/$path" "$BACKUP/$path"
    fi
  done <<< "$FILES"
  echo "ROLLBACK_DIRECTORY=$BACKUP"

  echo "========== BACKUP CURRENT MYSQL DATABASE =========="
  (
    cd "$APP"
    MYSQL_CNF_FILE="$BACKUP/mysql-client.cnf" MYSQL_NAME_FILE="$BACKUP/database-name" "$PHP" -r '
      require "vendor/autoload.php";
      $app = require "bootstrap/app.php";
      $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
      $c = config("database.connections.mysql");
      $db = (string) ($c["database"] ?? "");
      if (!preg_match("/^[A-Za-z0-9_]+$/D", $db)) {
          fwrite(STDERR, "BLOCKED=UNEXPECTED_DATABASE_NAME\n"); exit(1);
      }
      $quote = static fn ($value) => "\"".str_replace(
          ["\\", "\"", "\n", "\r"], ["\\\\", "\\\"", "", ""], (string) $value
      )."\"";
      $lines = ["[client]", "user=".$quote($c["username"] ?? ""), "password=".$quote($c["password"] ?? "")];
      if (!empty($c["unix_socket"])) $lines[] = "socket=".$quote($c["unix_socket"]);
      else {
          $lines[] = "host=".$quote($c["host"] ?? "127.0.0.1");
          $lines[] = "port=".((int) ($c["port"] ?? 3306));
      }
      if (file_put_contents(getenv("MYSQL_CNF_FILE"), implode("\n", $lines)."\n", LOCK_EX) === false
          || file_put_contents(getenv("MYSQL_NAME_FILE"), $db, LOCK_EX) === false) exit(1);
      chmod(getenv("MYSQL_CNF_FILE"), 0600);
      chmod(getenv("MYSQL_NAME_FILE"), 0600);
    '
  )
  DB_NAME="$(cat "$BACKUP/database-name")"
  "$MYSQLDUMP" --defaults-extra-file="$BACKUP/mysql-client.cnf" \
    --single-transaction --quick --no-tablespaces --default-character-set=utf8mb4 \
    "$DB_NAME" | gzip -1 > "$BACKUP/mysql-pre-upgrade.sql.gz"
  gzip -t "$BACKUP/mysql-pre-upgrade.sql.gz"
  test -s "$BACKUP/mysql-pre-upgrade.sql.gz"
  rm -f -- "$BACKUP/mysql-client.cnf"
  echo "MYSQL_BACKUP=VERIFIED"
  echo "MYSQL_BACKUP_PATH=$BACKUP/mysql-pre-upgrade.sql.gz"

  echo "========== APPLY SOURCE AND ADDITIVE MIGRATION =========="
  MAINT=1
  (cd "$APP"; runuser -u www -- "$PHP" artisan down --retry=60 --no-ansi)
  WRITTEN=1
  if [[ ! -d "$WORKSPACE_VIEW_DIR" ]]; then
    WORKSPACE_DIR_CREATED=1
    install -d -o root -g www -m 0755 "$WORKSPACE_VIEW_DIR"
    test -d "$WORKSPACE_VIEW_DIR" && test ! -L "$WORKSPACE_VIEW_DIR"
    echo "WORKSPACE_VIEW_DIRECTORY=CREATED"
  else
    echo "WORKSPACE_VIEW_DIRECTORY=EXISTING"
  fi
  while IFS='|' read -r path prior target; do
    # No new file may be installed into a missing or symlinked parent.
    parent="$APP/$(dirname "$path")"
    test -d "$parent" && test ! -L "$parent" || {
      echo "BLOCKED=UNSAFE_INSTALL_PARENT:$path"; exit 1;
    }
    install -o root -g www -m 0644 "$WORK/$path" "$APP/$path"
    test "$(git hash-object "$APP/$path")" = "$target"
  done <<< "$FILES"
  (
    cd "$APP"
    if [[ "$DATABASE_STATE" == ABSENT ]]; then
      runuser -u www -- "$PHP" artisan migrate --path="$MIGRATION_FILE" --force --no-ansi
    else
      echo "ADDITIVE_MIGRATION=ALREADY_PRESENT"
    fi
    runuser -u www -- "$PHP" -r '
      require "vendor/autoload.php";
      $app = require "bootstrap/app.php";
      $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
      if (!\Illuminate\Support\Facades\Schema::hasTable("prompt_quality_reviews")
        || !\Illuminate\Support\Facades\DB::table("migrations")->where(
          "migration", "2026_11_11_000100_create_prompt_quality_reviews"
        )->exists()) { fwrite(STDERR, "BLOCKED=PROMPT_QUALITY_MIGRATION_VERIFICATION\n"); exit(1); }
      foreach (["prompt_id", "reviewer_id", "result", "model_name", "test_scenario", "findings", "checks", "content_sha256"] as $column) {
        if (!\Illuminate\Support\Facades\Schema::hasColumn("prompt_quality_reviews", $column)) {
          fwrite(STDERR, "BLOCKED=PROMPT_QUALITY_COLUMN_MISSING\n"); exit(1);
        }
      }
      echo "PROMPT_QUALITY_SCHEMA=VERIFIED\n";
    '
    runuser -u www -- "$PHP" artisan route:clear --no-ansi
    runuser -u www -- "$PHP" artisan view:clear --no-ansi
    runuser -u www -- "$PHP" artisan view:cache --no-ansi
    runuser -u www -- "$PHP" artisan route:list --except-vendor --json --no-ansi > "$WORK/routes.json"
  )
  python3 - "$WORK/routes.json" <<'PYTHON_ROUTES'
import json
import sys
from pathlib import Path
routes=json.loads(Path(sys.argv[1]).read_text())
required={
 "dashboard": "GET", "account": "GET",
 "dashboard.prompt-quality": "GET",
 "account.prompt-quality": "GET",
 "account.prompts.quality": "GET",
 "account.prompts.quality.store": "POST",
 "account.prompts.submit": "POST",
 "dashboard.prompts.moderate": "POST",
}
for name, method in required.items():
    matching=[r for r in routes if r.get("name")==name and method in str(r.get("method","")).split("|")]
    if not matching:
        raise SystemExit("BLOCKED=MISSING_WORKSPACE_ROUTE:"+name)
print("PROMPT_QUALITY_ROUTES=VERIFIED")
PYTHON_ROUTES
  (cd "$APP"; runuser -u www -- "$PHP" artisan up --no-ansi)
  MAINT=0
else
  echo "TARGET_SOURCE_AND_DATABASE_ALREADY_PRESENT=VERIFIED"
fi

echo "========== VERIFY PUBLIC ORIGIN AND PRIVATE AUTH GUARDS =========="
for path in / /login /policies; do
  code="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 --resolve "$DOMAIN:443:127.0.0.1" \
    -o "$WORK/response" -w '%{http_code}' "https://$DOMAIN$path")"
  test "$code" = 200 || { echo "BLOCKED=PUBLIC_HTTP_$code:$path"; exit 1; }
done
for path in /dashboard /account /dashboard/prompt-quality /account/prompt-quality /account/settings; do
  code="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 --resolve "$DOMAIN:443:127.0.0.1" \
    -D "$WORK/headers" -o "$WORK/response" -w '%{http_code}' "https://$DOMAIN$path")"
  test "$code" = 302 || { echo "BLOCKED=PRIVATE_GUEST_GUARD:$path:$code"; exit 1; }
  grep -Eiq '^location: (https://[^/]*)?/login([[:space:]]|$)' "$WORK/headers" || {
    echo "BLOCKED=PRIVATE_LOGIN_REDIRECT:$path"; exit 1;
  }
done
curl --noproxy '*' -ksSf --connect-timeout 8 --max-time 25 --resolve "$DOMAIN:443:127.0.0.1" \
  "https://$DOMAIN/assets/app.css" -o "$WORK/app.css"
grep -Fq 'Prompt Quality Center: private human-editorial review UI' "$WORK/app.css"
grep -Fq 'Account Settings: compact responsive' "$WORK/app.css"
curl --noproxy '*' -ksSf --connect-timeout 8 --max-time 25 --resolve "$DOMAIN:443:127.0.0.1" \
  "https://$DOMAIN/assets/app.js" -o "$WORK/app.js"
grep -Fq 'Single reusable menu renderer across all private CRUD tables' "$WORK/app.js"
echo "PUBLIC_ORIGIN_AND_GUEST_GUARDS=VERIFIED"
echo "PROMPT_QUALITY_CENTER_UPGRADE=SUCCESS"
echo "ADMIN_CREATOR_TEST_QUEUE=INSTALLED"
echo "PRIVATE_REVIEW_EVIDENCE=APPEND_ONLY"
echo "STARTER_PROMPT_SUBMISSION_GATE=ENFORCED"
echo "PASS_INVALIDATION_ON_EDIT=INSTALLED"
echo "EXISTING_PROMPT_MODERATION=PRESERVED"
echo "DATABASE_MIGRATION=ADDITIVE_PROMPT_QUALITY_REVIEWS_ONLY"
echo "NGINX_PAYMENT_ENV=UNCHANGED"
if [[ -n "$BACKUP" ]]; then echo "ROLLBACK_DIRECTORY=$BACKUP"; fi
echo "NEXT=Test private quality history and draft-only publication gates in authenticated browsers"
