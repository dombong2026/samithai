# Samith AI — read-only actual-host evidence collection

This is an **operator-controlled** technical preflight, not authorization to deploy or write to a production host.

## aaPanel independent staging

After installing the exact candidate ZIP in a separate aaPanel staging root (never the samithai.com live root), run:

    SAMITH_HOST_CLASS=aapanel SAMITH_DOCUMENT_ROOT=/path/to/staging/public \
    SAMITH_TEST_URL=https://separate-test-host.example \
      bash support/host-acceptance.sh /path/to/staging

Review the Nginx vhost, MySQL version, running PHP-FPM binary, storage ownership, TLS and public-path restrictions separately. The script reads only filesystem metadata and performs HTTPS GET checks. It does not connect to MySQL, deploy code, write files, send email or issue a payment.

## cPanel shared-hosting independent staging

Create a separate cPanel account/database and upload the exact packaged ZIP. Point document root at public/ and perform installer and customer actions without CLI. When Terminal is available, run the same script with SAMITH_HOST_CLASS=cpanel. If unavailable, use File Manager, MultiPHP Manager, SSL/TLS and Samith AI System Center; provide the equivalent screenshots and verifiable evidence hashes.

The exit code is 1 for a failed technical preflight, 2 when checks require review, 0 for this limited checklist only. **Even 0 does not approve the actual host for release.** Independently verify package SHA-256, a real prior-release upgrade, full backup restoration, login/entitlement acceptance and reviewer identity.

Do not include server credentials, .env content, install tokens, SQL dumps or private customer data in tickets or GitHub logs.
