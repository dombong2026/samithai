#!/usr/bin/env bash
# Read-only Samith AI production release topology and cutover planner.
# No migrations, production writes, cron installation, Nginx reload or symlink changes.
set -Eeuo pipefail
set +x
umask 077

if [[ $# -ne 2 || "$1" != "--plan" || "$2" != /* ]]; then
  echo "Usage: bash support/samithai-editorial-cutover-plan.sh --plan /absolute/active-release" >&2
  exit 2
fi

RELEASE="$2"
EXPECTED="/www/wwwroot/samithai-releases"
SHARED="/var/lib/samithai-shared"
CURRENT="/www/wwwroot/samithai-current"
NGINX="/www/server/panel/vhost/nginx/samithai.com.conf"
PHP="/www/server/php/85/bin/php"
BLOCKERS=0

stop() { echo "BLOCKED=$1" >&2; exit 3; }
test "$(id -u)" -eq 0 || stop "Administrator authorization required"
test -d "$RELEASE" && test ! -L "$RELEASE" || stop "Invalid release directory"
RELEASE="$(realpath -e -- "$RELEASE")"
[[ "$RELEASE" == "$EXPECTED/"* ]] || stop "Outside approved releases root"
test -f "$RELEASE/artisan" && test -f "$RELEASE/.env" && test -f "$RELEASE/vendor/autoload.php" || stop "Laravel release files missing"
test -x "$PHP" || stop "Approved PHP binary missing"

"$PHP" -r '
require $argv[2]."/vendor/autoload.php";
$e=Dotenv\Dotenv::parse(file_get_contents($argv[1]));
if (($e["APP_ENV"]??"")!=="production"
    || ($e["DB_CONNECTION"]??"")!=="mysql") exit(3);
echo "PRODUCTION_ENVIRONMENT=VERIFIED\n";
' "$RELEASE/.env" "$RELEASE" || stop "Unexpected release identity"

echo "========== CURRENT PRODUCTION TOPOLOGY =========="
echo "PINNED_RELEASE=VERIFIED"
echo "APPROVED_SHARED_ROOT=$SHARED"
echo "STABLE_RELEASE_POINTER=$CURRENT"

if [[ -e "$SHARED" || -L "$SHARED" ]]; then
    echo "SHARED_STORAGE_PATH=ALREADY_PRESENT_REVIEW_BEFORE_CHANGES"
else
    echo "SHARED_STORAGE_PATH=NOT_YET_CREATED"
fi

if [[ -L "$CURRENT" ]]; then
    DEST="$(realpath -e "$CURRENT" 2>/dev/null || true)"
    if [[ "$DEST" == "$RELEASE" ]]; then
        echo "ACTIVE_POINTER=TARGETS_CURRENT_RELEASE"
    else
        echo "ACTIVE_POINTER=OTHER_RELEASE_REVIEW_REQUIRED"
        BLOCKERS=$((BLOCKERS+1))
    fi
elif [[ -e "$CURRENT" ]]; then
    echo "ACTIVE_POINTER=UNEXPECTED_REAL_DIRECTORY_REVIEW_REQUIRED"
    BLOCKERS=$((BLOCKERS+1))
else
    echo "ACTIVE_POINTER=NOT_YET_CONFIGURED"
fi

for sub in app framework/sessions framework/cache/data framework/cache/locks logs; do
    path="$RELEASE/storage/$sub"
    if [[ -L "$path" ]]; then
        echo "STORAGE_$sub=SYMLINK"
    elif [[ -d "$path" ]]; then
        echo "STORAGE_$sub=RELEASE_LOCAL_REQUIRES_CUTOVER"
    else
        echo "STORAGE_$sub=MISSING_REVIEW_REQUIRED"
        BLOCKERS=$((BLOCKERS+1))
    fi
done

if [[ -f "$NGINX" && ! -L "$NGINX" ]]; then
    if grep -Fq "root $RELEASE/public;" "$NGINX"; then
        echo "NGINX_ROOT=PINNED_RELEASE"
    elif grep -Fq "root $CURRENT/public;" "$NGINX"; then
        echo "NGINX_ROOT=STABLE_POINTER"
    else
        echo "NGINX_ROOT=UNKNOWN_REVIEW_REQUIRED"
        BLOCKERS=$((BLOCKERS+1))
    fi
else
    echo "NGINX_VHOST=NOT_FOUND_REVIEW_REQUIRED"
    BLOCKERS=$((BLOCKERS+1))
fi

echo "========== REQUIRED AUTHORIZED CUTOVER SEQUENCE =========="
echo "1=Obtain fresh, independently-restorable MySQL, media, configuration, and release backups"
echo "2=Complete restricted HTTPS and physical iPhone/Android/tablet acceptance on synthetic staging"
echo "3=Approve individual article text, SEO, image rights, and the new release commit"
echo "4=Drain traffic, stop old scheduler, and establish consistent source storage snapshot"
echo "5=Create protected shared storage and isolate per-release compiled views"
echo "6=Build signed/verified new release and link protected shared files"
echo "7=Run additive migrations once after backup; never reset production tables"
echo "8=Validate current article draft states and SEO image fallback"
echo "9=Configure stable symlink target and Nginx public root, then test Nginx and reload"
echo "10=Verify HTTPS browser, sessions, private assets, preview and rollback target"
echo "11=Enable exactly one release-aware cron entry under approved application identity"
echo "12=Verify no unapproved scheduled articles; retain old release for rollback"

echo "========== ROLLBACK GUARANTEES =========="
echo "ROLLBACK_POINTER=OLD_RELEASE_RETAINED_AND_IDENTIFIED"
echo "ROLLBACK_STORAGE=SHARED_DATA_WITH_COMPATIBILITY_REVIEW"
echo "ROLLBACK_DATABASE=NO_AUTOMATIC_DOWN_MIGRATIONS"
echo "ROLLBACK_CRON=DISABLE_NEW_ENTRY_BEFORE_SWITCHING_BACK"
echo "ROLLBACK_REQUIREMENT=SCHEMA_BACKWARD_COMPATIBILITY_OR_RESTORED_BACKUP"

echo "STATIC_BLOCKERS=$BLOCKERS"
echo "CUTOVER_AUTHORIZATION=NOT_GRANTED_BY_THIS_COMMAND"
echo "READ_ONLY_PLAN=COMPLETE"
echo "PRODUCTION_MIGRATION=NOT_PERFORMED"
echo "CRON_AND_NGINX_CONFIG=UNCHANGED"
