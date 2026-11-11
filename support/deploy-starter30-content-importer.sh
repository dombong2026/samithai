#!/usr/bin/env bash
# Samith AI: source-only installation of 30 unpublished starter prompts.
# --check is read-only. --apply installs source without database mutations.
set +x
set -Eeuo pipefail
umask 077

if [[ $# -ne 1 || ( "$1" != "--check" && "$1" != "--apply" ) ]]; then
  echo "Usage: bash deploy-starter30-content-importer.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
SOURCE="b8f46a76fbe04c754f2b81889a7d77e1c2f31787"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
PHP="/www/server/php/85/bin/php"
DOMAIN="samithai.com"
VHOST="/www/server/panel/vhost/nginx/$DOMAIN.conf"
FILES="$(cat <<'FILEHASHES'
app/Console/Commands/ImportStarterPromptDrafts.php|4af143668da25c2b02b235698e5c55fb913e0fa0
database/content/starter-2026-11/01-marketing-social-media.json|c08d81c070da83cf43672ecf1b9c2ae5b2c5d75a
database/content/starter-2026-11/02-education-teaching.json|600fce91585c059ca09bff3cac8cb95d21e8da94
database/content/starter-2026-11/03-business-productivity.json|7edff5236ced6a0a4a521e1c071418158e06b12f
database/content/starter-2026-11/04-coding-development.json|10ac16ee3e651d2d0d20dd97ebc482e2676b3db5
database/content/starter-2026-11/05-ai-image-video.json|12c8080e5a3297f06805339687536387858a3cd7
database/content/starter-2026-11/06-ecommerce-sales.json|ab4beeb49458b7c707100a604a874f7c08ffafc1
database/content/starter-2026-11/07-data-research.json|afb6830f933e096cc30d599eaa5260f5d5e8e826
FILEHASHES
)"
DIRS=(
 "$APP/app/Console"
 "$APP/app/Console/Commands"
 "$APP/database/content"
 "$APP/database/content/starter-2026-11"
)

echo "========== STARTER 30 SOURCE PREFLIGHT =========="
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

if [[ "$MISSING" -eq 8 && "$PRESENT" -eq 0 ]]; then
  SOURCE_STATE=NOT_INSTALLED
elif [[ "$MISSING" -eq 0 && "$PRESENT" -eq 8 ]]; then
  SOURCE_STATE=ALREADY_INSTALLED
else
  echo "BLOCKED=MIXED_SOURCE_STATE"; exit 1
fi
echo "SOURCE_FILES=8_OF_8_VERIFIED"
echo "SOURCE_STATE=$SOURCE_STATE"
if [[ "$MODE" == "--check" ]]; then
  echo "STARTER30_SOURCE_PREFLIGHT=PASSED"
  echo "APPLICATION_AND_DATABASE=UNCHANGED"
  exit 0
fi

exec 9>/root/samithai-starter30-importer.lock
flock -n 9 || { echo "BLOCKED=ANOTHER_IMPORTER_INSTALLATION"; exit 1; }
WORK="$(mktemp -d /root/samithai-starter30-stage.XXXXXXXX)"
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
  "$PHP" -l "$WORK/app/Console/Commands/ImportStarterPromptDrafts.php" >/dev/null

  python3 - "$WORK/database/content/starter-2026-11" <<'PYTHON_VALIDATE'
import json, sys
from pathlib import Path
root=Path(sys.argv[1])
files=sorted(root.glob('*.json'))
assert len(files)==7
expected=[6,5,5,5,4,3,2]
slugs=set()
for file, count in zip(files, expected):
    data=json.loads(file.read_text(encoding='utf-8'))
    assert data['schema']=='samithai.prompt-starter.v1'
    assert data['batch_id']=='samithai-starter-2026-11'
    assert len(data['products'])==count
    for p in data['products']:
        assert p['status']=='draft' and p['price']=='0.00' and p['license_type']=='regular'
        assert len(p['content']) >= 700 and len(p['review_checks']) >= 3
        assert p['slug'] not in slugs
        slugs.add(p['slug'])
assert len(slugs)==30
print('DRAFT_DATASET=30_UNIQUE_PROMPTS_VERIFIED')
PYTHON_VALIDATE
  echo "PINNED_GITHUB_BLOBS_AND_JSON=VERIFIED"

  BACKUP="$(mktemp -d /root/samithai-starter30-source-backup.XXXXXXXX)"
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
    grep -Eq '^samithai:import-starter-drafts([[:space:]]|$)'
)
echo "STARTER_IMPORT_COMMAND=DISCOVERED"
echo "STARTER30_SOURCE_INSTALL=SUCCESS"
echo "PROMPTS_CREATED=0"
echo "PUBLICATION_ACTIONS=NONE"
echo "DATABASE_MIGRATIONS=NONE"
echo "PAYMENT_NGINX_FRONTEND=UNCHANGED"
if [[ -n "$BACKUP" ]]; then echo "BACKUP_DIRECTORY=$BACKUP"; fi
echo "NEXT=Use read-only artisan --check, back up MySQL, then explicitly --apply to create draft records"
