#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/current-v3-encrypted-backup.sh
if bash support/current-v3-encrypted-backup.sh --apply >/dev/null 2>&1; then
  echo 'Invalid backup operation was accepted'
  exit 1
fi
echo 'V3_ENCRYPTED_BACKUP_SYNTAX=PASS'
