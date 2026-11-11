#!/usr/bin/env bash
# Samith AI: source-only installation of eight fully written, unpublished educational blog posts.
# --check is read-only. --apply installs source without database mutations.
set +x
set -Eeuo pipefail
umask 077

if [[ $# -ne 1 || ( "$1" != "--check" && "$1" != "--apply" ) ]]; then
  echo "Usage: bash deploy-educational-blog-drafts.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
SOURCE="3c85313a35deb27adf5c6f64a074e56b2e9b526e"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
PHP="/www/server/php/85/bin/php"
DOMAIN="samithai.com"
VHOST="/www/server/panel/vhost/nginx/$DOMAIN.conf"
FILES="$(cat <<'FILEHASHES'
app/Console/Commands/ImportEducationalBlogDrafts.php|31236a8d4b78c9082a2aca98deefd348460f15e1
database/content/educational-blog-2026-11/01-fundamentals.json|37ddd2a420b3e9f068c6d7df84b7e58b99b47ec8
database/content/educational-blog-2026-11/02-business-and-teaching.json|8c5a1c4af60b740c75b1af584e6da3680b01cdd6
database/content/educational-blog-2026-11/03-website-and-social.json|1c80d9a6c2a278872be7b127eaa97d3466534f50
database/content/educational-blog-2026-11/04-quality-and-agents.json|486712f56e1ca9451540ec8f6c4a21d639b4182a
FILEHASHES
)"
DIRS=(
 "$APP/app/Console"
 "$APP/app/Console/Commands"
 "$APP/database/content"
 "$APP/database/content/educational-blog-2026-11"
)

echo "========== EDUCATIONAL BLOG SOURCE PREFLIGHT =========="
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

if [[ "$MISSING" -eq 5 && "$PRESENT" -eq 0 ]]; then
  SOURCE_STATE=NOT_INSTALLED
elif [[ "$MISSING" -eq 0 && "$PRESENT" -eq 5 ]]; then
  SOURCE_STATE=ALREADY_INSTALLED
else
  echo "BLOCKED=MIXED_SOURCE_STATE"; exit 1
fi
echo "SOURCE_FILES=5_OF_5_VERIFIED"
echo "SOURCE_STATE=$SOURCE_STATE"
if [[ "$MODE" == "--check" ]]; then
  echo "EDUCATIONAL_BLOG_SOURCE_PREFLIGHT=PASSED"
  echo "APPLICATION_AND_DATABASE=UNCHANGED"
  exit 0
fi

exec 9>/root/samithai-educational-blog-installer.lock
flock -n 9 || { echo "BLOCKED=ANOTHER_EDUCATIONAL_BLOG_INSTALL"; exit 1; }
WORK="$(mktemp -d /root/samithai-educational-blog-stage.XXXXXXXX)"
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
  "$PHP" -l "$WORK/app/Console/Commands/ImportEducationalBlogDrafts.php" >/dev/null

  python3 - "$WORK/database/content/educational-blog-2026-11" <<'PYTHON_VALIDATE'
import json, re, sys
from pathlib import Path
folder=Path(sys.argv[1])
files=sorted(folder.glob('*.json'))
assert len(files)==4, 'Expected four article source files'
required=[
 'what-is-an-ai-prompt-beginners-guide',
 'how-to-write-better-prompts-for-ai-assistants',
 '20-practical-ai-prompts-for-small-businesses',
 'how-teachers-can-use-ai-to-prepare-lesson-plans',
 'how-to-create-a-professional-website-using-ai',
 '15-ai-prompts-for-social-media-marketing',
 'how-to-test-and-improve-an-ai-prompt',
 'ai-prompt-engineering-vs-ai-agents',
]
slugs=[]
for file in files:
    data=json.loads(file.read_text(encoding='utf-8'))
    assert data['schema']=='samithai.blog-drafts.v1'
    assert data['batch_id']=='samithai-education-blog-2026-11'
    assert len(data['articles'])==2
    for article in data['articles']:
        html=article['body_html']
        assert article['status']=='draft'
        assert article['seo_title'] and article['meta_description']
        assert article['primary_keyword'] and article['excerpt']
        assert article['word_count_estimate']>=900
        assert len(html)>=6000 and len(html)<=180000
        assert re.findall(r'<h2>(.*?)</h2>',html,flags=re.S)==article['outline']
        assert len(article['category_links'])>=2
        assert '<script' not in html.lower()
        for category in article['category_links']:
            assert 'https://samithai.com/categories/'+category['slug'] in html
        slugs.append(article['slug'])
assert slugs==required, 'Article count/slugs/order mismatch'
print('EDUCATIONAL_BLOG_DATASET=8_FULL_ARTICLES_VERIFIED')
PYTHON_VALIDATE
  echo "PINNED_GITHUB_BLOBS_AND_JSON=VERIFIED"

  BACKUP="$(mktemp -d /root/samithai-educational-blog-source-backup.XXXXXXXX)"
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
    grep -Eq '^samithai:import-educational-blogs([[:space:]]|$)'
)
echo "EDUCATIONAL_BLOG_IMPORT_COMMAND=DISCOVERED"
echo "EDUCATIONAL_BLOG_SOURCE_INSTALL=SUCCESS"
echo "BLOG_POSTS_CREATED=0"
echo "PUBLICATION_ACTIONS=NONE"
echo "DATABASE_MIGRATIONS=NONE"
echo "PAYMENT_NGINX_FRONTEND=UNCHANGED"
if [[ -n "$BACKUP" ]]; then echo "BACKUP_DIRECTORY=$BACKUP"; fi
echo "NEXT=Run --check, back up MySQL, import eight draft posts, then review categories and SEO editorial notes"
