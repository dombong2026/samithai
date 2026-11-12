#!/usr/bin/env bash
# This static gate deliberately does not access aaPanel or execute live mutations.
set -Eeuo pipefail
set +x

for file in \
  support/samithai-staging-editorial-seed.php \
  support/samithai-staging-prepare.sh \
  support/samithai-staging-https.sh; do
    test -s "$file" || { echo "MISSING=$file" >&2; exit 1; }
done
php -l support/samithai-staging-editorial-seed.php >/dev/null
test -s support/samithai-staging-tls-proxy.py
test -s support/samithai-staging-https-recover.sh
bash -n support/samithai-staging-https-recover.sh
python3 - <<'PY'
import ast
from pathlib import Path
ast.parse(Path('support/samithai-staging-tls-proxy.py').read_text())
print('STAGING_PYTHON_TLS_PROXY_SYNTAX=PASS')
PY
grep -Fq 'TLS_SOURCE="$CURRENT/support/samithai-staging-tls-proxy.py"' support/samithai-staging-https.sh
grep -Fq 'LISTEN_HOST = "127.0.0.1"' support/samithai-staging-tls-proxy.py
grep -Fq 'BACKEND_HOST = "127.0.0.1"' support/samithai-staging-tls-proxy.py
grep -Fq 'STAGING_TLS_RECOVERY=PASS' support/samithai-staging-https-recover.sh

bash -n support/samithai-staging-prepare.sh
bash -n support/samithai-staging-https.sh

grep -Fq 'STAGING_SOURCE_QA_ISOLATION' support/samithai-staging-prepare.sh ||
  grep -Fq 'QA_PRODUCTION_SEPARATION' support/samithai-staging-prepare.sh
grep -Fq 'DB_CONNECTION=sqlite' support/samithai-staging-prepare.sh
grep -Fq 'SESSION_SECURE_COOKIE=true' support/samithai-staging-prepare.sh
grep -Fq 'MAIL_MAILER=array' support/samithai-staging-prepare.sh
grep -Fq '127.0.0.1:' support/samithai-staging-https.sh
grep -Fq 'TLS_PUBLIC_TRUST_VALIDATION=STILL_REQUIRED' support/samithai-staging-https.sh
grep -Fq 'PRODUCTION_DATABASE_CRON_AND_VHOST=UNCHANGED' support/samithai-staging-https.sh
grep -Fq 'SIX_ARTICLES_STILL_DRAFT=YES' support/samithai-staging-editorial-seed.php
grep -Fq 'PRODUCTION_DATABASE_AND_MEDIA=NOT_ACCESSED' support/samithai-staging-editorial-seed.php

if grep -En '(^|[[:space:]])(migrate:fresh|migrate:refresh|db:wipe|DROP TABLE|TRUNCATE TABLE)' \
    support/samithai-staging-prepare.sh \
    support/samithai-staging-https.sh \
    support/samithai-staging-editorial-seed.php; then
  echo 'BLOCKED=DESTRUCTIVE_STAGING_COMMAND'
  exit 1
fi
if grep -En 'nginx -s reload|systemctl restart nginx|crontab -|/etc/cron.d/|/www/server/panel/vhost/nginx/.*>' \
    support/samithai-staging-prepare.sh support/samithai-staging-https.sh; then
  echo 'BLOCKED=PRODUCTION_SERVICE_MUTATION'
  exit 1
fi
echo 'PRIVATE_EDITORIAL_STAGING_STATIC_GATE=PASS'
