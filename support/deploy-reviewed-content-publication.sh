#!/usr/bin/env bash
# Samith AI: source-only guarded installation of staged editorial publication command.
# --check is read-only. --apply installs source without database mutations.
set +x
set -Eeuo pipefail
umask 077

if [[ $# -ne 1 || ( "$1" != "--check" && "$1" != "--apply" ) ]]; then
  echo "Usage: bash deploy-reviewed-content-publication.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
SOURCE="10ea1ee2bbc306c59d16efd60087d996ab5c24c4"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
PHP="/www/server/php/85/bin/php"
DOMAIN="samithai.com"
VHOST="/www/server/panel/vhost/nginx/$DOMAIN.conf"
FILES="$(cat <<'FILEHASHES'
app/Console/Commands/PublishReviewedCoreContent.php|a89edc27808076dc313ead0d50a98b5981134b4b
FILEHASHES
)"
DIRS=(
 "$APP/app/Console"
 "$APP/app/Console/Commands"
)

echo "========== EDITORIAL PUBLICATION SOURCE PREFLIGHT =========="
test "$(id -u)" -eq 0
for bin in git curl python3 runuser flock install rmdir; do command -v "$bin" >/dev/null; done
test -x "$PHP"
test -f "$APP/artisan"
test -f "$APP/vendor/autoload.php"
test -f "$APP/storage/app/installed.lock"
test -f "$APP/.env"
test -f "$VHOST"
id www >/dev/null
grep -Fq "root $APP/public;" "$VHOST" || { echo "BLOCKED=UNEXPECTED_NGINX_ROOT"; exit 1; }
test -d "$APP/app" && test ! -L "$APP/app"
test -d "$APP/database" && test ! -L "$APP/database"
if [[ -e "$APP/storage/framework/down" || -e "$APP/storage/framework/maintenance.php" ]]; then
  echo "BLOCKED=APP_ALREADY_IN_MAINTENANCE"; exit 1
fi
for dir in "${DIRS[@]}"; do
  if [[ -L "$dir" || ( -e "$dir" && ! -d "$dir" ) ]]; then
    echo "BLOCKED=UNSAFE_DIRECTORY:$dir"; exit 1
  fi
done

MISSING=0
PRESENT=0
while IFS='|' read -r path target; do
  file="$APP/$path"
  if [[ ! -e "$file" && ! -L "$file" ]]; then
    MISSING=$((MISSING+1))
  elif [[ -f "$file" && ! -L "$file" && "$(git hash-object "$file")" == "$target" ]]; then
    PRESENT=$((PRESENT+1))
  else
    echo "BLOCKED=UNKNOWN_OR_CHANGED_FILE:$path"; exit 1
  fi
done <<< "$FILES"

if [[ "$MISSING" -eq 1 && "$PRESENT" -eq 0 ]]; then
  SOURCE_STATE=NOT_INSTALLED
elif [[ "$MISSING" -eq 0 && "$PRESENT" -eq 1 ]]; then
  SOURCE_STATE=ALREADY_INSTALLED
else
  echo "BLOCKED=MIXED_SOURCE_STATE"; exit 1
fi
echo "SOURCE_FILES=1_OF_1_VERIFIED"
echo "SOURCE_STATE=$SOURCE_STATE"
if [[ "$MODE" == "--check" ]]; then
  echo "EDITORIAL_PUBLICATION_SOURCE_PREFLIGHT=PASSED"
  echo "APPLICATION_AND_DATABASE=UNCHANGED"
  exit 0
fi

exec 9>/root/samithai-editorial-release-installer.lock
flock -n 9 || { echo "BLOCKED=ANOTHER_EDITORIAL_RELEASE_INSTALL"; exit 1; }
WORK="$(mktemp -d /root/samithai-editorial-release-stage.XXXXXXXX)"
BACKUP=""
WRITTEN=0
CREATED_DIRS=()
finish() {
  status=$?
  trap - EXIT INT TERM
  set +e
  if [[ "$status" -ne 0 && "$WRITTEN" -eq 1 ]]; then
    echo "ROLLBACK=STARTED"
    recovered=1
    while IFS='|' read -r path target; do
      file="$APP/$path"
      if [[ -f "$file" && ! -L "$file" && "$(git hash-object "$file" 2>/dev/null)" == "$target" ]]; then
        rm -f -- "$file" || recovered=0
      elif [[ -e "$file" || -L "$file" ]]; then
        echo "BLOCKED_ROLLBACK_FILE_CHANGED=$path"; recovered=0
      fi
    done <<< "$FILES"
    for ((idx=${#CREATED_DIRS[@]}-1; idx>=0; idx--)); do
      rmdir -- "${CREATED_DIRS[$idx]}" >/dev/null 2>&1 || true
    done
    if [[ "$recovered" -eq 1 ]]; then echo "SOURCE_ROLLBACK=VERIFIED"
    else echo "SOURCE_ROLLBACK=NEEDS_MANUAL_REVIEW"; fi
  fi
  rm -rf -- "$WORK"
  exit "$status"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ "$SOURCE_STATE" == NOT_INSTALLED ]]; then
  echo "========== VERIFY PINNED GITHUB BLOBS =========="
  while IFS='|' read -r path target; do
    mkdir -p "$WORK/$(dirname "$path")"
    curl -fsSL --retry 3 --proto '=https' --connect-timeout 10 --max-time 90 \
      "https://raw.githubusercontent.com/dombong2026/samithai/$SOURCE/$path" -o "$WORK/$path"
    test "$(git hash-object "$WORK/$path")" = "$target" || { echo "BLOCKED=PINNED_BLOB_MISMATCH:$path"; exit 1; }
  done <<< "$FILES"
  "$PHP" -l "$WORK/app/Console/Commands/PublishReviewedCoreContent.php" >/dev/null

  python3 - "$WORK/app/Console/Commands/PublishReviewedCoreContent.php" <<'PYTHON_VALIDATE'
from pathlib import Path
s=Path(__import__('sys').argv[1]).read_text()
assert "PUBLISH-SIX-REVIEWED-PAGES" in s
assert "PUBLISH-FIRST-REVIEWED-ARTICLE" in s
assert "PUBLISH-SECOND-REVIEWED-ARTICLE" in s
assert "lockForUpdate()" in s and "DB::transaction(" in s
assert "OTHER_SIX_EDUCATIONAL_ARTICLES=UNCHANGED_DRAFTS" in s
print("EDITORIAL_PUBLICATION_GATES=SOURCE_VERIFIED")
PYTHON_VALIDATE
  echo "PINNED_GITHUB_BLOBS_AND_JSON=VERIFIED"

  BACKUP="$(mktemp -d /root/samithai-editorial-release-source-backup.XXXXXXXX)"
  echo "BACKUP_DIRECTORY=$BACKUP"
  WRITTEN=1
  for dir in "${DIRS[@]}"; do
    if [[ ! -d "$dir" ]]; then
      install -d -o root -g www -m 0755 "$dir"
      CREATED_DIRS+=("$dir")
      test -d "$dir" && test ! -L "$dir"
    fi
  done
  while IFS='|' read -r path target; do
    parent="$APP/$(dirname "$path")"
    test -d "$parent" && test ! -L "$parent" || { echo "BLOCKED=UNSAFE_PARENT:$path"; exit 1; }
    install -o root -g www -m 0644 "$WORK/$path" "$APP/$path"
    test "$(git hash-object "$APP/$path")" = "$target"
  done <<< "$FILES"
  echo "IMPORTER_SOURCE_FILES=INSTALLED"
fi

echo "========== VERIFY READ-ONLY ARTISAN COMMAND DISCOVERY =========="
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan list --raw --no-ansi |
    grep -Eq '^samithai:publish-reviewed-content([[:space:]]|$)'
)
echo "EDITORIAL_PUBLICATION_COMMAND=DISCOVERED"
echo "EDITORIAL_PUBLICATION_SOURCE_INSTALL=SUCCESS"
echo "CONTENT_RECORDS_MODIFIED=0"
echo "PUBLICATION_ACTIONS=NONE"
echo "DATABASE_MIGRATIONS=NONE"
echo "PAYMENT_NGINX_FRONTEND=UNCHANGED"
if [[ -n "$BACKUP" ]]; then echo "BACKUP_DIRECTORY=$BACKUP"; fi
echo "NEXT=Run the read-only editorial readiness audit; review previews and publish in three separately approved stages"
