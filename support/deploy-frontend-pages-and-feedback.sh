#!/usr/bin/env bash
# Samith AI: global feedback + Frontend Pages management source upgrade.
# --check is read-only. --apply installs a pinned 21-file application-only patch.
# No DB migrations, DB restoration, payment changes or Nginx modifications.
set +x
set -Eeuo pipefail
umask 077

if [[ $# -ne 1 || ( "$1" != "--check" && "$1" != "--apply" ) ]]; then
  echo "Usage: bash support/deploy-frontend-pages-and-feedback.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
SOURCE="633ca50a1f092ed320f7b0b5b262a9d4592af518"
APP="/www/wwwroot/samithai-releases/3c9bf3fda1d0df308b94a60b006f36c437e7bf49"
PHP="/www/server/php/85/bin/php"
DOMAIN="samithai.com"
VHOST="/www/server/panel/vhost/nginx/$DOMAIN.conf"
FILES="$(cat <<'FILEHASHES'
app/Http/Controllers/Dashboard/AppearanceController.php|49eb07e41c2a13b6a0a3ac9fade0a0e8b39ec8f5|e5e5705a075da1edcb0657286bf34312780d9ace
app/Http/Controllers/Dashboard/FrontendPageController.php|-|ea1d70ee995c246e14967ed455ce8bb2dc4d45d8
app/Support/FrontendPageCopy.php|-|340e43d3d1818bf38543e229b3c85802dc2e6616
public/assets/app.css|aaf2bb751d6bbc85b77d3cde5ac3a1676fb36d80|dc8d9418d1a37f3776caa2d32b77782b864e8115
public/assets/app.js|69af028b80ad55aeac940668bc833c4bb4cc2c8f|8fd1834100a7354e1c3a67306542c5e0ae3cc6d9
resources/views/auth/forgot-password.blade.php|89cd73040092e1847d8e97fc4aab4a9be5e637a0|2e8cc5c51ca2be6cda493f1ed1a6d7c033582fd5
resources/views/auth/login.blade.php|5908614b5deb88bc88f28b77c2f2852eddd1d9a9|ef4f79960e39e40f44f5718daa64d322779eb394
resources/views/auth/register.blade.php|d20d7c17e2d6d50c9609628b614aebe0c22c353d|42114a96dca6884686308ed4712c5013797df515
resources/views/auth/reset-password.blade.php|0e366e6249487338c48d582d45e52b36c346d8c6|bde35778268c6fe8ef37c0e0ca58eb824d774cd5
resources/views/auth/verify-email.blade.php|214aa927f20bcb9f25a777389ca8fdb3abdd95bc|b1cbe1a6f9107da3df67cf2f6d828fa71ff3154e
resources/views/dashboard/appearance.blade.php|55b42906bfdc686c38285f54e104f696ba40e5cd|729749ddb1d16e4e4c3e194c9d015356041446ba
resources/views/layouts/site.blade.php|2c41f82223c99e37364284b777e5687a21183260|3d44786d486c7da38f094209e2ac34864a7a7add
resources/views/marketplace/category.blade.php|338c4c8aec278534c3749d132bccae73e3674828|55a74f42bb0ea39974847b3ae43e783cac0453c0
resources/views/marketplace/home.blade.php|b70a139f8a20c46704877f753ff67bf3045356cf|ef0448927398e798957fa2a2b0a9ba4a6a262121
resources/views/marketplace/index.blade.php|5c9ca24ac302df71f1494eadf581cc90c7e13ca3|a80a36617c5e69f61ce5a77a8893f4ab52da8cef
resources/views/marketplace/policies.blade.php|5b62a1bb95baaf5b73671a94f3a9a6b2319148af|29a143ec37cb434028a54ce139f6fd837ffbdee9
resources/views/marketplace/posts.blade.php|a36b9293e4ab1ef44de788f976946926f5a17cf0|c971512bf8d1af589dae3e74caba77e3b8fee815
resources/views/marketplace/show.blade.php|b07809abe919ff49904cab1e6f99bed75f49780c|6bb68a7f347b17bcf664f5d59acb2d988ef4e2aa
resources/views/partials/feedback.blade.php|96451690fb88bd911e006e3b2024b222cd9a2a95|e32cb61d32fedb9149d386a66068b23309a4effd
resources/views/support/center.blade.php|ba184ca2cd3ab757d543ccc42e6d4909471a15d9|00ad87d69b1da611c63517c43595c0033ec3b69f
routes/web.php|f3250c3dff719de816cc437a5d9bc0beeb2373fe|b7852bd826e02b7f78b64e6ed2b0cafdb9356e8a
FILEHASHES
)"

echo "========== SAMITH AI FRONTEND PAGES & FEEDBACK PRECHECK =========="
test "$(id -u)" -eq 0
for command_name in git curl python3 runuser flock; do command -v "$command_name" >/dev/null; done
test -x "$PHP"
test -f "$APP/artisan" && test -f "$APP/vendor/autoload.php"
test -f "$APP/.env" && test -f "$APP/storage/app/installed.lock"
test -f "$VHOST"
id www >/dev/null
grep -Fq "root $APP/public;" "$VHOST" || {
  echo "BLOCKED: production Nginx root mismatch"; exit 1
}
if [[ -e "$APP/storage/framework/down" || -e "$APP/storage/framework/maintenance.php" ]]; then
  echo "BLOCKED: another maintenance action is already active"; exit 1
fi

BEFORE=0
AFTER=0
while IFS='|' read -r path previous target; do
  file="$APP/$path"
  if [[ "$previous" == "-" && ! -e "$file" && ! -L "$file" ]]; then
    BEFORE=$((BEFORE+1))
  elif [[ -f "$file" && ! -L "$file" ]]; then
    actual="$(git hash-object "$file")"
    if [[ "$actual" == "$previous" ]]; then
      BEFORE=$((BEFORE+1))
    elif [[ "$actual" == "$target" ]]; then
      AFTER=$((AFTER+1))
    else
      echo "BLOCKED: unexpected production file: $path"
      echo "ACTUAL_GIT_SHA=$actual"
      exit 1
    fi
  else
    echo "BLOCKED: missing, symlink or unexpected file: $path"
    exit 1
  fi
done <<< "$FILES"
if [[ "$BEFORE" -eq 21 && "$AFTER" -eq 0 ]]; then
  SOURCE_STATE="PREVIOUS"
elif [[ "$BEFORE" -eq 0 && "$AFTER" -eq 21 ]]; then
  SOURCE_STATE="ALREADY_UPDATED"
else
  echo "BLOCKED: mixed old/new source ($BEFORE previous and $AFTER new)"
  exit 1
fi
echo "SOURCE_FILES=21_OF_21_VERIFIED"
echo "SOURCE_STATE=$SOURCE_STATE"

DB="$(
  cd "$APP"
  runuser -u www -- "$PHP" -r '
    require "vendor/autoload.php";
    $app=require "bootstrap/app.php";
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if (config("database.default")!=="mysql"
      || !\Illuminate\Support\Facades\Schema::hasTable("site_settings")
      || !\Illuminate\Support\Facades\Schema::hasTable("content_entries")
      || !\Illuminate\Support\Facades\Schema::hasTable("audit_events")
      || !\Illuminate\Support\Facades\Schema::hasTable("prompt_saves")) {
      fwrite(STDERR,"BLOCKED: required existing Laravel tables are missing\n");
      exit(1);
    }
    echo "OK";
  '
)"
test "$DB" = "OK"
echo "REQUIRED_EXISTING_DATABASE_TABLES=VERIFIED"

if [[ "$MODE" == "--check" ]]; then
  echo "FRONTEND_PAGES_PREFLIGHT=PASSED"
  echo "PRODUCTION_FILES=UNCHANGED"
  echo "DATABASE=UNCHANGED"
  echo "NEW_DATABASE_MIGRATIONS=NONE"
  exit 0
fi

exec 9>/root/samithai-frontend-pages-upgrade.lock
flock -n 9 || { echo "BLOCKED: another frontend upgrade is active"; exit 1; }
WORK="$(mktemp -d /root/samithai-frontend-pages-src.XXXXXXXX)"
BACKUP=""
CHANGED=0
MAINTENANCE=0

finish() {
  code=$?
  trap - EXIT INT TERM
  set +e
  if [[ "$code" -ne 0 && "$CHANGED" -eq 1 ]]; then
    echo "FAILED=ATTEMPTING_OLD_APPLICATION_CODE_RESTORE"
    while IFS='|' read -r path previous target; do
      if [[ "$previous" == "-" ]]; then
        rm -f -- "$APP/$path"
      else
        cp -p -- "$BACKUP/$path" "$APP/$path"
      fi
    done <<< "$FILES"
    (
      cd "$APP"
      runuser -u www -- "$PHP" artisan route:clear --no-ansi
      runuser -u www -- "$PHP" artisan view:clear --no-ansi
      runuser -u www -- "$PHP" artisan view:cache --no-ansi
    ) >/dev/null 2>&1
    echo "OLD_SOURCE_ROLLBACK=ATTEMPTED"
    echo "ROLLBACK_DIRECTORY=$BACKUP"
  fi
  if [[ "$MAINTENANCE" -eq 1 ]]; then
    (cd "$APP"; runuser -u www -- "$PHP" artisan up --no-ansi) >/dev/null 2>&1
    echo "MAINTENANCE_EXIT=ATTEMPTED"
  fi
  rm -rf -- "$WORK"
  exit "$code"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ "$SOURCE_STATE" == "PREVIOUS" ]]; then
  echo "========== DOWNLOAD PINNED PATCH AND VERIFY ALL BLOBS =========="
  while IFS='|' read -r path previous target; do
    mkdir -p "$WORK/$(dirname "$path")"
    curl -fsSL --retry 3 --proto '=https' --connect-timeout 10 --max-time 60 \
      "https://raw.githubusercontent.com/dombong2026/samithai/$SOURCE/$path" \
      -o "$WORK/$path"
    test "$(git hash-object "$WORK/$path")" = "$target" || {
      echo "BLOCKED: pinned source integrity failed for $path";exit 1;
    }
    if [[ "$path" == *.php && "$path" != *.blade.php ]]; then
      "$PHP" -l "$WORK/$path" >/dev/null
    fi
  done <<< "$FILES"
  echo "PINNED_SOURCE_BLOBS_AND_PHP_SYNTAX=VERIFIED"

  BACKUP="$(mktemp -d /root/samithai-frontend-pages-backup.XXXXXXXX)"
  while IFS='|' read -r path previous target; do
    if [[ "$previous" != "-" ]]; then
      mkdir -p "$BACKUP/$(dirname "$path")"
      cp -p -- "$APP/$path" "$BACKUP/$path"
    fi
  done <<< "$FILES"
  echo "ROLLBACK_DIRECTORY=$BACKUP"

  echo "========== BRIEF APPLICATION MAINTENANCE =========="
  MAINTENANCE=1
  (cd "$APP"; runuser -u www -- "$PHP" artisan down --retry=60 --no-ansi)

  echo "========== INSTALL FRONTEND PAGES AND FEEDBACK PATCH =========="
  CHANGED=1
  while IFS='|' read -r path previous target; do
    install -o root -g www -m 0644 "$WORK/$path" "$APP/$path"
    test "$(git hash-object "$APP/$path")" = "$target"
  done <<< "$FILES"

  (
    cd "$APP"
    runuser -u www -- "$PHP" artisan route:clear --no-ansi
    runuser -u www -- "$PHP" artisan view:clear --no-ansi
    runuser -u www -- "$PHP" artisan view:cache --no-ansi
    runuser -u www -- "$PHP" artisan route:list --except-vendor --no-ansi > "$WORK/routes.txt"
  )
  grep -Fq 'dashboard.appearance.frontend.update' "$WORK/routes.txt"
  grep -Fq 'dashboard.appearance.frontend.reset' "$WORK/routes.txt"
  echo "NEW_APPEARANCE_ROUTES_AND_BLADE=VERIFIED"

  (cd "$APP"; runuser -u www -- "$PHP" artisan up --no-ansi)
  MAINTENANCE=0
else
  echo "ALL_TARGET_FILES_ALREADY_INSTALLED=SKIPPING_WRITE"
fi

echo "========== CHECK REAL LIVE ORIGIN =========="
for page in "/" "/login" "/prompts" "/blog" "/policies" "/support-center"; do
  http="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 \
    --resolve "$DOMAIN:443:127.0.0.1" \
    -o "$WORK/page.html" -w '%{http_code}' "https://$DOMAIN$page")"
  test "$http" = "200" || { echo "BLOCKED: $page HTTP $http";exit 1; }
done
echo "STOREFRONT_ROUTES=HTTP_200"

for page in "/dashboard" "/dashboard/appearance" "/account"; do
  http="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 \
    --resolve "$DOMAIN:443:127.0.0.1" \
    -D "$WORK/headers" -o "$WORK/page.html" -w '%{http_code}' "https://$DOMAIN$page")"
  test "$http" = "302" || { echo "BLOCKED: guest $page returned HTTP $http";exit 1; }
  grep -Eiq '^location: (https://[^/]*)?/login([[:space:]]|$)' "$WORK/headers" || {
    echo "BLOCKED: $page did not enforce authentication";exit 1;
  }
done
echo "PRIVATE_DASHBOARD_AND_ACCOUNT_GUEST_GUARDS=VERIFIED"

css="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 \
  --resolve "$DOMAIN:443:127.0.0.1" \
  -o "$WORK/style.css" -w '%{http_code}' "https://$DOMAIN/assets/app.css")"
test "$css" = "200"
grep -Fq '.frontend-pages-panel' "$WORK/style.css"
js="$(curl --noproxy '*' -ksS --connect-timeout 8 --max-time 25 \
  --resolve "$DOMAIN:443:127.0.0.1" \
  -o "$WORK/script.js" -w '%{http_code}' "https://$DOMAIN/assets/app.js")"
test "$js" = "200"
grep -Fq 'Global feedback notifications' "$WORK/script.js"
grep -Fq 'frontend-config-dialog' "$WORK/script.js"
echo "FRONTEND_PAGES_STYLES_AND_NOTIFICATION_SCRIPT=VERIFIED"

CHANGED=0
echo "========== FRONTEND PAGES & NOTIFICATIONS UPGRADE SUCCESS =========="
echo "GLOBAL_SUCCESS_FEEDBACK_AUTO_DISMISS=INSTALLED"
echo "GLOBAL_ERROR_FEEDBACK_AUTO_DISMISS=INSTALLED"
echo "FRONTEND_PAGES_TABLE_AND_POPUP=INSTALLED"
echo "BUILTIN_COPY_AND_SECTION_SETTINGS=INSTALLED"
echo "CUSTOM_PAGE_CRUD=EXISTING_SECURE_MANAGER_REUSED"
echo "LEGAL_CONTENT_GOVERNANCE=PRESERVED"
echo "ACCOUNT_AND_ADMIN_ACCESS_CONTROL=PRESERVED"
echo "DATABASE_MIGRATIONS=UNCHANGED"
echo "PAYMENT_NGINX_ENV=UNCHANGED"
if [[ -n "$BACKUP" ]]; then echo "ROLLBACK_DIRECTORY=$BACKUP";fi
echo "NEXT=Sign in as administrator and test saving/resetting a template and custom-page CRUD"
