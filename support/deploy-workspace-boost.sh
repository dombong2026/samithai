#!/usr/bin/env bash
# Samith AI: guarded workspace dashboard + account upgrade.
# New schema: one additive workspace_preferences table.
# No existing table changes, payment changes, .env or nginx changes.
set +x
set -Eeuo pipefail
umask 077

if [[ $# -ne 1 || ( "$1" != "--check" && "$1" != "--apply" ) ]]; then
  echo "Usage: bash deploy-workspace-boost.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
SOURCE="a56cd27696d439c62c358107cd00cc0853099985"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
PHP="/www/server/php/85/bin/php"
DOMAIN="samithai.com"
VHOST="/www/server/panel/vhost/nginx/$DOMAIN.conf"
MIGRATION="2026_11_10_000100_create_workspace_preferences"
MIGRATION_FILE="database/migrations/$MIGRATION.php"
FILES="$(cat <<'FILEHASHES'
app/Http/Controllers/AccountController.php|ec953ee6bbe4628eb4251d477829562e763099f1|4d3ae70f329d8a7dd9cbdbaabffb268262f2a60b
app/Http/Controllers/Dashboard/OverviewController.php|ca745b5429b2d8a437c0b1e4818b0f5c576c626c|d4bae0fba0264d346740c721c9b95d0934cce865
app/Http/Controllers/WorkspaceShortcutsController.php|-|fac4c01662cfda60033a6ee79b937705302a73dd
app/Models/WorkspacePreference.php|-|fde04c885646f2a1f834030784ebd6e5a6ae090f
app/Support/WorkspaceActivity.php|-|7960e0c7fffb39d4df5bdfde49f10fd6943df5c7
app/Support/WorkspaceLaunchpad.php|-|9081b64c8e817ea39efe88076e5a9ecf49a85549
database/migrations/2026_11_10_000100_create_workspace_preferences.php|-|4abacd0fd776efa1e2e91e9408e79bff2ffda714
public/assets/app.css|ee1e7038ffb352a7992aaea33d45d84fd95afe03|70e0acbb07dc10e32367b9a7fd51a397f2334fe6
public/assets/app.js|6377fa9a8fae23bb049bdebf03d32e75d037ba58|c9999be6d15327aedbf0aac265432d8380219e79
resources/views/account/index.blade.php|008d5fc67829983a626af68fc9f9e2d1ca19a788|469c24fd7ad7011bb2f574a5937f1cc97b6ffebe
resources/views/dashboard/overview.blade.php|5dbfab3cebc7f4dee83ab819a433ca5634e9b133|3c64d815b58b8aefbba14cb498410c1bce52c23d
resources/views/partials/workspace-activity.blade.php|-|71e5ca8de448eb6459a41ab69a8ce850df72d947
resources/views/partials/workspace-launchpad.blade.php|-|22ab997b71b68a60bd6aae79e22d48e2d3fccf29
routes/web.php|36f8d9c5aadc8eb1eaf46a61303778793a5bcd11|f2df77dc3c8ed932eda7cfef08182452274bf81c
FILEHASHES
)"

echo "========== SAMITH AI WORKSPACE BOOST PREFLIGHT =========="
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
if [[ "$BEFORE" -eq 14 && "$AFTER" -eq 0 ]]; then SOURCE_STATE=PREVIOUS
elif [[ "$BEFORE" -eq 0 && "$AFTER" -eq 14 ]]; then SOURCE_STATE=ALREADY_UPDATED
else
  echo "BLOCKED=MIXED_PRODUCTION_FILES"
  echo "PREVIOUS=$BEFORE TARGET=$AFTER"
  exit 1
fi
echo "SOURCE_FILES=14_OF_14_VERIFIED"
echo "SOURCE_STATE=$SOURCE_STATE"

DATABASE_STATE="$(cd "$APP"; runuser -u www -- "$PHP" -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config("database.default") !== "mysql"
    || !\Illuminate\Support\Facades\Schema::hasTable("migrations")
    || !\Illuminate\Support\Facades\Schema::hasTable("users")
    || !\Illuminate\Support\Facades\Schema::hasTable("audit_events")
    || !\Illuminate\Support\Facades\Schema::hasTable("prompt_saves")) {
    fwrite(STDERR, "BLOCKED=UNEXPECTED_MYSQL_SCHEMA\n"); exit(1);
}
$table = \Illuminate\Support\Facades\Schema::hasTable("workspace_preferences");
$migrated = \Illuminate\Support\Facades\DB::table("migrations")
  ->where("migration", "2026_11_10_000100_create_workspace_preferences")->exists();
if ($table !== $migrated) {
    fwrite(STDERR, "BLOCKED=PARTIAL_WORKSPACE_MIGRATION\n"); exit(1);
}
if ($table) {
    foreach (["user_id", "context", "shortcuts"] as $col) {
        if (!\Illuminate\Support\Facades\Schema::hasColumn("workspace_preferences", $col)) {
            fwrite(STDERR, "BLOCKED=WORKSPACE_PREFERENCES_SCHEMA_MISMATCH\n"); exit(1);
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
  echo "WORKSPACE_BOOST_PREFLIGHT=PASSED"
  echo "PRODUCTION_FILES_AND_DATABASE=UNCHANGED"
  exit 0
fi

exec 9>/root/samithai-workspace-boost.lock
flock -n 9 || { echo "BLOCKED=CONCURRENT_DEPLOYMENT"; exit 1; }
WORK="$(mktemp -d /root/samithai-workspace-boost-src.XXXXXXXX)"
BACKUP=""
WRITTEN=0
MAINT=0
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

  BACKUP="$(mktemp -d /root/samithai-workspace-boost-backup.XXXXXXXX)"
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
  while IFS='|' read -r path prior target; do
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
      if (!\Illuminate\Support\Facades\Schema::hasTable("workspace_preferences")
        || !\Illuminate\Support\Facades\DB::table("migrations")->where(
          "migration", "2026_11_10_000100_create_workspace_preferences"
        )->exists()) { fwrite(STDERR, "BLOCKED=WORKSPACE_MIGRATION_VERIFICATION\n"); exit(1); }
      echo "WORKSPACE_PREFERENCES_SCHEMA=VERIFIED\n";
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
 "workspace.shortcuts.update": "PUT",
 "workspace.shortcuts.reset": "DELETE",
}
for name, method in required.items():
    matching=[r for r in routes if r.get("name")==name and method in str(r.get("method","")).split("|")]
    if not matching:
        raise SystemExit("BLOCKED=MISSING_WORKSPACE_ROUTE:"+name)
print("WORKSPACE_BOOST_ROUTES=VERIFIED")
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
for path in /dashboard /account /dashboard/appearance; do
  code="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 --resolve "$DOMAIN:443:127.0.0.1" \
    -D "$WORK/headers" -o "$WORK/response" -w '%{http_code}' "https://$DOMAIN$path")"
  test "$code" = 302 || { echo "BLOCKED=PRIVATE_GUEST_GUARD:$path:$code"; exit 1; }
  grep -Eiq '^location: (https://[^/]*)?/login([[:space:]]|$)' "$WORK/headers" || {
    echo "BLOCKED=PRIVATE_LOGIN_REDIRECT:$path"; exit 1;
  }
done
curl --noproxy '*' -ksSf --connect-timeout 8 --max-time 25 --resolve "$DOMAIN:443:127.0.0.1" \
  "https://$DOMAIN/assets/app.css" -o "$WORK/app.css"
grep -Fq 'Workspace Boost:' "$WORK/app.css"
curl --noproxy '*' -ksSf --connect-timeout 8 --max-time 25 --resolve "$DOMAIN:443:127.0.0.1" \
  "https://$DOMAIN/assets/app.js" -o "$WORK/app.js"
grep -Fq 'Account-scoped workspace shortcut picker' "$WORK/app.js"
echo "PUBLIC_ORIGIN_AND_GUEST_GUARDS=VERIFIED"
echo "WORKSPACE_BOOST_UPGRADE=SUCCESS"
echo "ADMIN_WEEKLY_PULSE=INSTALLED"
echo "ACCOUNT_ACTION_CENTER=INSTALLED"
echo "PERSONAL_SHORTCUTS=SERVER_PERSISTED"
echo "RECENT_AUDIT_ACTIVITY=SCOPED"
echo "DATABASE_MIGRATION=ADDITIVE_WORKSPACE_PREFERENCES_ONLY"
echo "NGINX_PAYMENT_ENV=UNCHANGED"
if [[ -n "$BACKUP" ]]; then echo "ROLLBACK_DIRECTORY=$BACKUP"; fi
echo "NEXT=Test Quick Launch customization/reset and account role permissions in the browser"
