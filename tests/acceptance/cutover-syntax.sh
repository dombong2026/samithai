#!/usr/bin/env bash
set -Eeuo pipefail
bash -n support/aapanel-production-cutover.sh
if bash support/aapanel-production-cutover.sh --invalid >/dev/null 2>&1; then
  echo 'ERROR: invalid cutover mode accepted'
  exit 1
fi
echo 'AA_PANEL_CUTOVER_SCRIPT_SYNTAX=PASS'
