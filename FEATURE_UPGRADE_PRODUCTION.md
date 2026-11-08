# Samith AI — review workflow and catalog upgrade

The new moderation review history requires an additive production database migration. The marketplace sort selector requires only new PHP/Blade code. This upgrade is therefore NOT safe to deploy as a template-only hotfix.

## Before a live aaPanel upgrade

1. Confirm the production Nginx root and installed application path match the deployment history.
2. Run the check-only script: bash support/production-feature-upgrade-preflight.sh --check.
3. Obtain an encrypted and independently verified off-host MySQL and private-media backup, including the necessary confidential app encryption key. Restore it separately and compare the recovery inventory; pre-upgrade v2 and post-upgrade v3 inventories intentionally cannot be compared without a new baseline.
4. Keep the current working release and Nginx vhost backup, plan an operator-approved maintenance window and test rollback on a separate environment.
5. Only after those prerequisites are satisfied, install the exact reviewed code, apply the additive migration with php artisan migrate --force as the website user, compile views, smoke-test creator/admin review actions and public catalog filters, then capture a new recovery inventory.

Do not use migrate:fresh, migrate:refresh, db:wipe, reinstall the marketplace, reset .env, or turn on payment/email flags. The included preflight does not perform or authorize any production deployment.

## Source-level CI

Laravel feature tests cover feedback validation, ownership boundaries, escaped notes, resubmission, review timeline, combined marketplace filters and sorting. The normal MySQL/browser CI and disposable backup drill are not a replacement for independent actual-host upgrade and off-site restoration tests.
