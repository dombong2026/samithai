#!/usr/bin/env bash
# Stage updated PR #6 in a NEW versioned staging release.
# No staging pointer switch, no live DB changes, no Nginx/cron edits.
set -Eeuo pipefail
set +x
umask 077
if [[ $# != 3 || ( "$1" != --check && "$1" != --stage ) ||
    ! "$2" =~ ^[a-f0-9]{40}$ || ! "$3" =~ ^[a-f0-9]{40}$ ]]; then
  echo "Usage: bash script.sh --check|--stage PR6_SHA MAIN_SHA" >&2
  exit 2
fi
MODE="$1"
HEAD="$2"
BASE="$3"
REPO="dombong2026/samithai"
BRANCH="feature/seo-editorial-workflow-20261111"
LAB="/var/lib/samithai-editorial-staging-20261112"
QA="/var/lib/samithai-editorial-mysql-qa-20261112"
OLD="$LAB/releases/candidate"
PREFIX="$(printf '%s' "$HEAD" | cut -c1-12)"
NEW="$LAB/releases/pr6-$PREFIX"
SHARED="$LAB/shared"
PHP="/www/server/php/85/bin/php"

blocked() { echo "BLOCKED=$*" >&2; echo "PRODUCTION=UNCHANGED"; exit 3; }
[[ "$(id -u)" == 0 && -x "$PHP" ]] || blocked "Root and PHP 8.5 required"
for bin in gh git runuser flock sha256sum python3 cut; do
  command -v "$bin" >/dev/null || blocked "Missing $bin"
done
id www >/dev/null || blocked "Missing www account"
[[ -d "$LAB" && ! -L "$LAB" &&
   -d "$LAB/releases" && ! -L "$LAB/releases" &&
   -d "$OLD" && ! -L "$OLD" &&
   -L "$LAB/current" && "$(realpath -e "$LAB/current")" == "$OLD" ]] ||
    blocked "Original staging release or active pointer changed"
[[ -f "$SHARED/staging.sqlite" && ! -L "$SHARED/staging.sqlite" &&
   -f "$SHARED/.env" && -f "$SHARED/storage/app/staging-credentials.json" &&
   -f "$QA/vendor/autoload.php" && -f "$QA/composer.lock" ]] ||
    blocked "Original synthetic staging state missing"
[[ ! -e "$NEW" && ! -L "$NEW" ]] ||
    blocked "Target release already exists; refuse overwrite"
[[ "$(gh api "repos/$REPO/pulls/6" --jq '.head.sha')" == "$HEAD" &&
   "$(gh api "repos/$REPO/pulls/6" --jq '.base.sha')" == "$BASE" &&
   "$(gh api "repos/$REPO/pulls/6" --jq '.draft')" == true &&
   "$(gh api "repos/$REPO/pulls/6" --jq '.state')" == open ]] ||
    blocked "Pinned open draft PR #6 SHA/base mismatch"

"$PHP" -r '
require $argv[2]."/vendor/autoload.php";
$e=Dotenv\Dotenv::parse(file_get_contents($argv[1]));
if (($e["APP_ENV"]??"")!=="staging" ||
    ($e["DB_CONNECTION"]??"")!=="sqlite" ||
    ($e["DB_DATABASE"]??"")!==$argv[3] ||
    ($e["MAIL_MAILER"]??"")!=="array" ||
    ($e["CACHE_STORE"]??"")!=="file" ||
    ($e["SESSION_DRIVER"]??"")!=="file" ||
    ($e["QUEUE_CONNECTION"]??"")!=="sync") exit(4);
echo "DISPOSABLE_STAGING_DATABASE=VERIFIED\n";
' "$SHARED/.env" "$QA" "$SHARED/staging.sqlite" ||
    blocked "Synthetic staging environment mismatch"

echo "ORIGINAL_STAGING_COMMIT=$(git -C "$OLD" rev-parse HEAD)"
echo "REVIEWED_RELEASE_COMMIT=$HEAD"
echo "NEW_STAGING_RELEASE=$NEW"
if [[ "$MODE" == --check ]]; then
  echo "NEW_RELEASE=NOT_YET_CREATED"
  echo "ACTIVE_RELEASE_POINTER=UNCHANGED"
  echo "PRODUCTION=UNCHANGED"
  exit 0
fi

exec 9>/run/lock/samithai-editorial-staging-stage-latest.lock
flock -n 9 || blocked "Another staging operation is active"
gh repo clone "$REPO" "$NEW" -- --branch "$BRANCH" --single-branch --depth 1 --quiet ||
    blocked "Failed to clone new candidate; partial release preserved for inspection"
[[ "$(git -C "$NEW" rev-parse HEAD)" == "$HEAD" ]] ||
    blocked "New candidate SHA mismatch"
[[ -f "$NEW/composer.lock" ]] ||
    blocked "New candidate dependency lock is missing"
NEW_LOCK="$(sha256sum "$NEW/composer.lock" | cut -d' ' -f1)"
QA_LOCK="$(sha256sum "$QA/composer.lock" | cut -d' ' -f1)"
[[ "$NEW_LOCK" == "$QA_LOCK" ]] ||
    blocked "Composer dependencies changed; a fresh isolated vendor build is required"
[[ ! -e "$NEW/.env" && ! -L "$NEW/.env" &&
   ! -L "$NEW/storage" && -d "$NEW/storage" &&
   -f "$NEW/public/assets/app.css" &&
   -f "$NEW/public/assets/app.js" ]] ||
    blocked "Unapproved secrets/storage or missing asset bundle"

cp -a "$QA/vendor" "$NEW/vendor"
mv -T "$NEW/storage" "$NEW/storage.source-skeleton"
install -d -m 755 "$NEW/storage/framework/cache"
install -d -m 770 "$NEW/storage/framework/views"
install -d -m 770 "$NEW/bootstrap/cache"
for sub in app framework/sessions framework/cache/data framework/cache/locks logs; do
  [[ -d "$SHARED/storage/$sub" && ! -L "$SHARED/storage/$sub" ]] ||
      blocked "Unexpected staging shared storage: $sub"
  ln -s "$SHARED/storage/$sub" "$NEW/storage/$sub"
done
ln -s "$SHARED/.env" "$NEW/.env"
chown -R root:www "$NEW"
find "$NEW" -type d -exec chmod g+rx {} +
find "$NEW" -type f -exec chmod g+r {} +
chown -R www:www "$NEW/storage/framework/views" "$NEW/bootstrap/cache"

echo "========== ISOLATED NEW SOURCE ACCEPTANCE =========="
(
  cd "$NEW"
  runuser -u www -- env -i \
    PATH=/www/server/php/85/bin:/usr/local/bin:/usr/bin:/bin HOME="$LAB" \
    "$PHP" artisan route:list --path=dashboard --no-ansi > "$LAB/pr6-current-routes.txt"
  runuser -u www -- env -i \
    PATH=/www/server/php/85/bin:/usr/local/bin:/usr/bin:/bin HOME="$LAB" \
    "$PHP" artisan view:cache --no-ansi > "$LAB/pr6-current-blade-compile.txt"
  runuser -u www -- env -i \
    PATH=/www/server/php/85/bin:/usr/local/bin:/usr/bin:/bin HOME="$LAB" \
    "$PHP" artisan samithai:review-six-educational-drafts --json --strict \
      > "$LAB/pr6-current-six-review.json"
) || blocked "New release acceptance failed; inspect staging-only outputs"

python3 - "$LAB/pr6-current-six-review.json" <<'PY'
import json,sys
with open(sys.argv[1]) as f: r=json.load(f)
assert r["status"]=="AUTOMATIC_CHECKS_PASS"
assert r["article_count"]==6 and r["automatic_finding_count"]==0
for item in r["articles"]:
    assert item["public_status"]=="draft"
    assert item["featured_media_verified"] and item["seo_saved"]
    assert item["social_image_strategy"]=="assigned_featured_fallback"
    assert not item["current_human_approval"]
print("CURRENT_PR6_EDUCATIONAL_ACCEPTANCE=PASS")
PY

python3 - "$NEW" "$HEAD" "$BASE" <<'PY'
import os,json,sys
from pathlib import Path
target=Path(sys.argv[1])/"staging-release.json"
data={"format":"samithai/pr6-staged/v1","commit":sys.argv[2],
      "base":sys.argv[3],"status":"STAGED_ONLY",
      "production_mutations":False,"human_approval":False}
fd=os.open(target,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
with os.fdopen(fd,"w") as fh: json.dump(data,fh,indent=2)
print("NEW_RELEASE_MANIFEST=VERIFIED")
PY

[[ "$(realpath -e "$LAB/current")" == "$OLD" ]] ||
    blocked "Original staging pointer unexpectedly changed"
echo "UPDATED_PR6_RELEASE=READY_FOR_PRIVATE_BROWSER_ACCEPTANCE"
echo "UPDATED_PR6_SHA=$HEAD"
echo "STAGING_POINTER=UNCHANGED"
echo "STAGING_ARTICLES=UNPUBLISHED"
echo "PRODUCTION_MYSQL_NGINX_CRON_MEDIA=UNCHANGED"
