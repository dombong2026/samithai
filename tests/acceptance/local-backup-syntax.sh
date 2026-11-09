#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/production-local-backup.sh
if bash support/production-local-backup.sh --apply >/dev/null 2>&1; then
  echo 'Invalid mode unexpectedly accepted'
  exit 1
fi
echo 'LOCAL_BACKUP_SCRIPT=SYNTAX_VALID'
