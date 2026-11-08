# Samith AI — Minimal login recovery link alignment

This is a two-file UI adjustment only. It moves "Forgot your password?" into the same options row as "Remember me", above the orange "Sign in" button. It does not alter registration, password reset, any backend route, account access, database, credentials, or Nginx.

Source fix commit: `d32c6e0ec9ec7e2b9f795deb974ea8b0f4badce8`. The production operator can run `bash support/deploy-login-recovery-row.sh --check` and, after the preflight passes, `--apply`.

The script verifies original Git blob fingerprints from the previously deployed auth eye-icon update, fetches only the pinned login Blade template and its existing CSS file, verifies new fingerprints, preserves rollback copies under private /root, compiles Blade views and verifies the HTML/CSS over local HTTPS. It automatically restores the previous UI files if verification fails.

Desktop/mobile browser alignment is additionally checked in GitHub Actions. The live device should also be reviewed in dark/light modes. No database migration or full release replacement is required.
