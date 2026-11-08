# Samith AI — independent host and recovery acceptance

GitHub CI is not acceptance for a real cPanel or aaPanel installation. Record operator, hosting vendor, PHP/MySQL versions, test domain, release commit, package SHA256, evidence locations and date.

## cPanel and aaPanel, individually
- [ ] Extract the signed package *outside* webroot; document root targets the public/ directory only.
- [ ] PHP extensions, OpenSSL, Fileinfo, DOM, PDO MySQL, and secure permissions pass.
- [ ] Prove .env, Git, vendor, private media, and installation token are not web-accessible.
- [ ] First-run form rejects wrong tokens/database credentials and refuses any database with preexisting tables, even without user accounts; fresh empty DB installs once.
- [ ] One administrator can authenticate; installer cannot run again.
- [ ] Login, registration, password reset with real SMTP, media, prompt approval, free checkout, tickets and legal records work.
- [ ] HTTPS, backups, server logs, retention and timezones have been checked.

## Rollback and recovery on isolated staging clone
- [ ] Export a complete MySQL dump, media directory and private .env with separately protected keys.
- [ ] Record SHA256 digests. Restore into a *different* test database and directory.
- [ ] Compare user, prompt, entitlement, order, policy and legal version counts and sampled IDs.
- [ ] Apply upgrade migrations to the disposable clone; test previously issued entitlements.
- [ ] Simulate interrupted deployment; restore backup and confirm application functionality.
- [ ] Preserve audit records, order records and immutable accepted versions.
- [ ] Record actual recovery duration, tester and evidence. No automatic production rollback.

## Optional read-only preflight on an operator-approved host
  SAMITH_DOCUMENT_ROOT=/path/to/site/public SAMITH_TEST_URL=https://your-test-domain \
    bash support/release-preflight.sh /path/to/application

The script only reports; it does not modify the host. On no-SSH cPanel, perform equivalent checks through File Manager and PHP settings.

## Signoff
cPanel: [ ] Passed [ ] Failed [ ] Untested
aaPanel: [ ] Passed [ ] Failed [ ] Untested
Backup restoration: [ ] Passed [ ] Failed [ ] Untested
Upgrade rollback: [ ] Passed [ ] Failed [ ] Untested
Operator, timestamp, findings and evidence:
