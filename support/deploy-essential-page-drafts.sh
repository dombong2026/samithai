#!/usr/bin/env bash
# Samith AI: source-only installation of six complete unpublished informational pages.
# --check is read-only. --apply installs source without database mutations.
set +x
set -Eeuo pipefail
umask 077

if [[ $# -ne 1 || ( "$1" != "--check" && "$1" != "--apply" ) ]]; then
  echo "Usage: bash deploy-essential-page-drafts.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
SOURCE="b385e898e47f576d8c3ffaccc55c69a02b55c8c1"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
PHP="/www/server/php/85/bin/php"
DOMAIN="samithai.com"
VHOST="/www/server/panel/vhost/nginx/$DOMAIN.conf"
FILES="$(cat <<'FILEHASHES'
app/Console/Commands/ImportEssentialPageDrafts.php|264c7461ac706c82adb2664d0bb2f850c2b3a061
database/content/essential-pages-2026-11/01-platform-and-creators.json|4a607ee9c3f3638c768eb650160f3ca5d841467f
database/content/essential-pages-2026-11/02-trust-and-guidance.json|39f7a1c0c018aed8b6dad376843c431fb8af77c8
FILEHASHES
)"
DIRS=(
 "$APP/app/Console"
 "$APP/app/Console/Commands"
 "$APP/database/content"
 "$APP/database/content/essential-pages-2026-11"
)

echo "========== ESSENTIAL PAGE CONTENT PREFLIGHT =========="
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

if [[ "$MISSING" -eq 3 && "$PRESENT" -eq 0 ]]; then
  SOURCE_STATE=NOT_INSTALLED
elif [[ "$MISSING" -eq 0 && "$PRESENT" -eq 3 ]]; then
  SOURCE_STATE=ALREADY_INSTALLED
else
  echo "BLOCKED=MIXED_SOURCE_STATE"; exit 1
fi
echo "SOURCE_FILES=3_OF_3_VERIFIED"
echo "SOURCE_STATE=$SOURCE_STATE"
if [[ "$MODE" == "--check" ]]; then
  echo "ESSENTIAL_PAGE_SOURCE_PREFLIGHT=PASSED"
  echo "APPLICATION_AND_DATABASE=UNCHANGED"
  exit 0
fi

exec 9>/root/samithai-essential-pages-installer.lock
flock -n 9 || { echo "BLOCKED=ANOTHER_ESSENTIAL_PAGE_INSTALL"; exit 1; }
WORK="$(mktemp -d /root/samithai-essential-pages-stage.XXXXXXXX)"
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
  "$PHP" -l "$WORK/app/Console/Commands/ImportEssentialPageDrafts.php" >/dev/null

  python3 - "$WORK/database/content/essential-pages-2026-11" <<'PYTHON_VALIDATE'
import json, re, sys
from pathlib import Path
folder=Path(sys.argv[1])
files=sorted(folder.glob('*.json'))
assert len(files)==2, 'Expected exactly two content files'
expected=['about-samith-ai','how-it-works','become-a-creator','prompt-quality-standards',
          'ai-tools-and-compatibility','responsible-ai-usage']
pages=[]
for file in files:
    data=json.loads(file.read_text(encoding='utf-8'))
    assert data['schema']=='samithai.page-drafts.v1'
    assert data['batch_id']=='samithai-core-pages-2026-11'
    assert len(data['pages'])==3
    for p in data['pages']:
        assert len(p['body_html'])>=3000 and len(p['body_html'])<=180000
        assert p['title'] and p['excerpt'] and p['seo_title'] and p['meta_description']
        assert len(p['review_notes'])>=2
        assert len(re.findall(r'<h2>',p['body_html']))>=3
        assert '<script' not in p['body_html'].lower()
        pages.append(p['slug'])
assert pages==expected, 'Page ordering/slugs mismatch'
print('ESSENTIAL_PAGE_DATASET=6_COMPLETE_DRAFTS_VERIFIED')
PYTHON_VALIDATE
  echo "PINNED_GITHUB_BLOBS_AND_JSON=VERIFIED"

  BACKUP="$(mktemp -d /root/samithai-essential-pages-source-backup.XXXXXXXX)"
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
    grep -Eq '^samithai:import-essential-pages([[:space:]]|$)'
)
echo "ESSENTIAL_PAGE_IMPORT_COMMAND=DISCOVERED"
echo "ESSENTIAL_PAGE_SOURCE_INSTALL=SUCCESS"
echo "PAGE_RECORDS_CREATED=0"
echo "PUBLICATION_ACTIONS=NONE"
echo "DATABASE_MIGRATIONS=NONE"
echo "PAYMENT_NGINX_FRONTEND=UNCHANGED"
if [[ -n "$BACKUP" ]]; then echo "BACKUP_DIRECTORY=$BACKUP"; fi
echo "NEXT=Run --check, back up MySQL and explicitly import six draft-only pages; review and publish individually"
