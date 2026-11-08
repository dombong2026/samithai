#!/usr/bin/env bash
# No external requests; this script checks that unset evidence cannot pass.
set -Eeuo pipefail
bash -n support/host-acceptance.sh
result="$(SAMITH_TEST_URL='' SAMITH_DOCUMENT_ROOT='' SAMITH_HOST_CLASS='ci' bash support/host-acceptance.sh . 2>&1)" && code=0 || code=$?
printf '%s\n' "$result"
if [[ "$code" == 0 ]]; then echo 'ERROR: source checkout falsely accepted as a real host'; exit 1; fi
grep -q 'EXTERNAL_HOST_PREFLIGHT' <<< "$result"
grep -q 'REVIEW' <<< "$result"
echo 'HOST_PREFLIGHT_FAILS_CLOSED=VERIFIED'
