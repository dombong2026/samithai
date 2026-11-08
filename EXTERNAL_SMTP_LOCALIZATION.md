# Samith AI — External SMTP and localization acceptance

## Staging-only SMTP probe

External mail delivery is **not** verified by code configuration or GitHub CI. For an operator-approved isolated staging clone, first arrange a provider account with the exact sender domain and observe SPF/DKIM/DMARC, TLS policy, bounce handling, and API/SMTP rate limits. Configure MAIL_MAILER=smtp (or an authorized transactional provider), a valid non-placeholder sender, and use APP_ENV=staging.

Only when ready for an operator-controlled recipient:
- Set SAMITH_EMAIL_DELIVERY_ENABLED=true for staging, and SAMITH_SMTP_PROBE_ENABLED=true for that one acceptance period.
- Run \`php artisan samith:smtp-acceptance\` without --send to check configuration. It returns review/nonzero, not an inbox pass.
- Explicitly run \`php artisan samith:smtp-acceptance --send --to=your-controlled-mailbox@example.org\` to attempt exactly one plain acceptance message. No password reset token or customer information is sent.
- Verify the message actually appears in the inbox, read the message source/headers for SPF/DKIM/DMARC/TLS, test real password reset and email verification flows, and record masked/hashed evidence. Disable the probe flag after testing.
- Never use Mail log/array/failover-to-log mailers as proof of external email delivery. Never send from production merely for a CI check.

This command is a **controlled test mechanism only**; it does not mark external SMTP acceptance complete or create a provider connection.

## Localization coverage report

Run \`php support/acceptance/localization-audit.php\` to get a per-locale list of missing keys, blank values, extra keys and placeholder mismatches. \`--strict\` returns nonzero on structural issues. A complete key table is **not** native-language validation; independent Khmer, English, Chinese and remaining language reviewers must separately assess grammar, right-to-left layout where relevant, product/legal content, mobile readability and workflow parity.

The source-controlled \`support/acceptance/pending.json\` keeps both of these gates pending until independently documented review.
