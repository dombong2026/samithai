#!/usr/bin/env bash
# Read-only --check; --prepare writes ONLY to dedicated synthetic staging paths.
set -Eeuo pipefail
set +x
umask 077

if [[ $# -ne 3 || ( "$1" != --check && "$1" != --prepare ) ||
      ! "$2" =~ ^[a-f0-9]{40}$ || ! "$3" =~ ^[a-f0-9]{40}$ ]]; then
    echo "Usage: bash support/samithai-staging-prepare.sh --check|--prepare PR_HEAD_SHA MAIN_SHA" >&2
    exit 2
fi
MODE="$1"; HEAD="$2"; BASE="$3"
REPO="dombong2026/samithai"
BRANCH="feature/seo-editorial-workflow-20261111"
QA="/var/lib/samithai-editorial-mysql-qa-20261112"
PROD="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
LAB="/var/lib/samithai-editorial-staging-20261112"
SHARED="$LAB/shared"
CAND="$LAB/releases/candidate"
ROLL="$LAB/releases/rollback"
PHP="/www/server/php/85/bin/php"
fail() { echo "BLOCKED=$*" >&2; exit 3; }

echo "========== NON-PRODUCTION RELEASE PREFLIGHT =========="
[[ "$(id -u)" == 0 && -x "$PHP" ]] || fail "Root/PHP missing"
for command in gh git runuser flock python3 sha256sum; do
    command -v "$command" >/dev/null || fail "Missing $command"
done
id www >/dev/null || fail "Web account missing"
[[ -d "$QA/.git" && ! -L "$QA" &&
   -f "$QA/vendor/autoload.php" && -f "$QA/composer.lock" &&
   -f "$QA/.env" && -f "$PROD/.env" ]] || fail "QA/prod source unavailable"
[[ "$(git -C "$QA" rev-parse HEAD)" == "f85c9b1a0c09f604e70823f2d3306b78c6471acf" ]] ||
    fail "QA source commit changed"
[[ ! -e "$LAB" && ! -L "$LAB" ]] || fail "Isolated staging lab already exists"

"$PHP" -r '
require $argv[3]."/vendor/autoload.php";
$q=Dotenv\Dotenv::parse(file_get_contents($argv[1]));
$p=Dotenv\Dotenv::parse(file_get_contents($argv[2]));
if (($q["APP_ENV"]??"")!=="testing"
    || ($q["DB_DATABASE"]??"")!=="samithai_eqa_20261112"
    || ($p["APP_ENV"]??"")!=="production"
    || ($p["DB_DATABASE"]??"")==($q["DB_DATABASE"]??"")) exit(2);
echo "QA_PRODUCTION_SEPARATION=PASS\n";
' "$QA/.env" "$PROD/.env" "$QA" || fail "Environment identity mismatch"

[[ "$(gh api "repos/$REPO/pulls/6" --jq '.head.sha')" == "$HEAD" ]] ||
    fail "PR #6 head moved"
[[ "$(gh api "repos/$REPO/pulls/6" --jq '.base.sha')" == "$BASE" ]] ||
    fail "PR #6 base moved"
[[ "$(gh api "repos/$REPO/pulls/6" --jq '.draft')" == true ]] ||
    fail "PR #6 must remain in draft"
[[ "$(gh api "repos/$REPO/pulls/6" --jq '.state')" == open ]] ||
    fail "PR #6 is no longer open"
"$PHP" -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 2);' ||
    fail "pdo_sqlite is required"

echo "PINNED_CANDIDATE=$HEAD"
echo "PINNED_ROLLBACK_SOURCE=$BASE"
echo "PRODUCTION_CHANGES=NONE"
if [[ "$MODE" == --check ]]; then
    echo "STAGING_PREPARED=NO"
    exit 0
fi

exec 9>/run/lock/samithai-editorial-staging-prepare.lock
flock -n 9 || fail "Another staging preparation is running"

echo "========== CREATE ISOLATED VERSIONED RELEASES =========="
install -d -m 750 -o root -g www "$LAB"
install -d -m 755 "$LAB/releases"
echo "ISOLATED_LAB_CREATED=$LAB"

gh repo clone "$REPO" "$CAND" -- \
    --branch "$BRANCH" --single-branch --depth 1 --quiet ||
    fail "Candidate GitHub clone failed"
gh repo clone "$REPO" "$ROLL" -- \
    --branch main --single-branch --depth 1 --quiet ||
    fail "Rollback GitHub clone failed"
[[ "$(git -C "$CAND" rev-parse HEAD)" == "$HEAD" &&
   "$(git -C "$ROLL" rev-parse HEAD)" == "$BASE" ]] ||
    fail "Pinned source checkout mismatch"

for RELEASE in "$CAND" "$ROLL"; do
    [[ ! -e "$RELEASE/.env" && ! -L "$RELEASE/.env" ]] ||
        fail "Unexpected committed environment secrets"
    [[ -f "$RELEASE/composer.lock" ]] || fail "Composer lock missing"
    [[ "$(sha256sum "$RELEASE/composer.lock" | cut -d' ' -f1)" == \
       "$(sha256sum "$QA/composer.lock" | cut -d' ' -f1)" ]] ||
        fail "QA vendor does not match release dependency lock"
    cp -a "$QA/vendor" "$RELEASE/vendor"
    [[ -f "$RELEASE/public/assets/app.css" &&
       -f "$RELEASE/public/assets/app.js" ]] ||
        fail "Frontend assets missing"
    [[ "$RELEASE" == "$LAB/releases/"* && ! -L "$RELEASE/storage" ]] ||
        fail "Unsafe release storage location"
    rm -rf -- "$RELEASE/storage"
    install -d -m 755 "$RELEASE/storage/framework/cache"
    install -d -m 770 "$RELEASE/storage/framework/views"
    install -d -m 770 "$RELEASE/bootstrap/cache"
    chown -R root:www "$RELEASE"
    find "$RELEASE" -type d -exec chmod g+rx {} +
    find "$RELEASE" -type f -exec chmod g+r {} +
    chown -R www:www "$RELEASE/storage/framework/views" "$RELEASE/bootstrap/cache"
done

echo "========== CREATE PROTECTED SHARED STATE =========="
install -d -m 750 -o root -g www "$SHARED"
install -d -m 700 -o www -g www "$SHARED/storage"
for sub in app framework/sessions framework/cache/data framework/cache/locks logs; do
    install -d -m 700 -o www -g www "$SHARED/storage/$sub"
done
for RELEASE in "$CAND" "$ROLL"; do
    for sub in app framework/sessions framework/cache/data framework/cache/locks logs; do
        ln -s "$SHARED/storage/$sub" "$RELEASE/storage/$sub"
    done
done

install -m 600 -o www -g www /dev/null "$SHARED/staging.sqlite"
printf 'staging installed\n' > "$SHARED/storage/app/installed.lock"
chown www:www "$SHARED/storage/app/installed.lock"
chmod 600 "$SHARED/storage/app/installed.lock"

APPKEY="$("$PHP" -r 'echo base64_encode(random_bytes(32));')"
cat > "$SHARED/.env" <<EOF
APP_NAME="Samith AI Private Editorial Staging"
APP_ENV=staging
APP_DEBUG=false
APP_KEY=base64:$APPKEY
APP_URL=https://127.0.0.1:8843
APP_LOCALE=en
DB_CONNECTION=sqlite
DB_DATABASE=$SHARED/staging.sqlite
CACHE_STORE=file
SESSION_DRIVER=file
SESSION_COOKIE=samithai_editorial_staging_session
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=sync
MAIL_MAILER=array
FILESYSTEM_DISK=local
LOG_CHANNEL=single
EOF
chown root:www "$SHARED/.env"
chmod 640 "$SHARED/.env"
for RELEASE in "$CAND" "$ROLL"; do
    ln -s "$SHARED/.env" "$RELEASE/.env"
done

ln -s "$CAND" "$LAB/current"
printf '%s\n' "$HEAD" > "$LAB/candidate.sha"
printf '%s\n' "$BASE" > "$LAB/rollback.sha"

echo "========== MIGRATE AND SEED ONLY SYNTHETIC SQLITE =========="
(
    cd "$CAND"
    runuser -u www -- env -i \
        PATH=/www/server/php/85/bin:/usr/local/bin:/usr/bin:/bin \
        HOME="$LAB" \
        "$PHP" artisan migrate --force --no-interaction --no-ansi
) > "$LAB/migrations.log" 2>&1 ||
    fail "Staging migration failed; preserved private log in lab"

(
    cd "$CAND"
    runuser -u www -- env -i \
        PATH=/www/server/php/85/bin:/usr/local/bin:/usr/bin:/bin \
        HOME="$LAB" \
        "$PHP" support/samithai-staging-editorial-seed.php
) || fail "Staging seed failed; lab preserved"

(
    cd "$CAND"
    runuser -u www -- env -i \
        PATH=/www/server/php/85/bin:/usr/local/bin:/usr/bin:/bin \
        HOME="$LAB" \
        "$PHP" artisan samithai:review-six-educational-drafts --strict --json
) > "$LAB/editorial-readiness.json" ||
    fail "Staging editorial readiness failed; inspect private report"

python3 - "$LAB/editorial-readiness.json" <<'PY'
import json,sys
with open(sys.argv[1]) as f: report=json.load(f)
assert report['status']=='AUTOMATIC_CHECKS_PASS'
assert report['article_count']==6
assert report['automatic_finding_count']==0
assert all(r['public_status']=='draft'
           and r['social_image_strategy']=='assigned_featured_fallback'
           and not r['current_human_approval'] for r in report['articles'])
print('SIX_DRAFT_SEO_MEDIA_FALLBACK=PASS')
PY

echo "========== ISOLATED STAGING READY =========="
echo "STAGING_RELEASES=2"
echo "STAGING_ACTIVE_POINTER=CANDIDATE"
echo "STAGING_SHARED_STORAGE=READY"
echo "STAGING_CREDENTIALS=$SHARED/credentials.json"
echo "SIX_ARTICLES_UNPUBLISHED=VERIFIED"
echo "PRODUCTION_MYSQL_MEDIA_NGINX_CRON=UNCHANGED"
echo "NEXT=Start the isolated HTTPS preview using the separate staging HTTPS launcher"
