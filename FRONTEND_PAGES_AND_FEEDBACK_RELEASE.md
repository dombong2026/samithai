# Samith AI — Frontend Pages and global feedback release

## Functional scope

- Global flash success/error notifications use accessible live regions, a visible dismiss button and automatic expiry (4.5 seconds for success; 9 seconds for errors). Timer pauses on hover and keyboard focus. Browser caches are bypassed for the changed public JavaScript.
- Appearance now includes a compact searchable **Frontend Pages** table and native Configure dialogs.
- Thirteen built-in public templates have typed validated editable text and supported display toggles: global shell, homepage, prompt catalog, category listing, prompt detail labels, blog listing, policy listing, support center, login, registration, password recovery, password reset and email verification. All changes are escaped in public Blade templates and written to `site_settings` JSON values using keys `frontend_copy.{page}`.
- Reset defaults deletes only that template's override and leaves site content and other settings intact. Every admin update/reset records an audit event.
- Dynamic blog/policy/support article rows navigate to the existing secure Post/Policy/Knowledge managers. Versioned legal license body edits remain in Legal Review; no route allows changing governed legal documents from a page-copy popup.
- Custom `/pages/{slug}` content is listed with modal-based Create Draft, Configure, Preview, Publish/Unpublish, and Delete actions, backed by the preexisting audited Content Manager functions. HTML sanitization and owner/role gates are preserved.

## Production safety

The user's production installation should match the prior verified account + creator UI release. Deploy only the 21 manifest files using the pinned script from this GitHub commit, and run --check before --apply. The script refuses any mixed/custom source state, downloads fixed source blobs, verifies every Git hash and PHP source syntax, keeps root-only originals, briefly enables Laravel maintenance mode while installing, compiles views and clears routes, then verifies origin HTTP/guest guards/CSS/JS. If post-install verification fails, previous code is restored as an attempt; no automatic database restoration or destructive migration happens.

Existing `site_settings`, `content_entries`, and `audit_events` tables are reused. **No new DB migration is required**, and no changes are made to Nginx, PHP-FPM, payment providers, customer entitlements or existing published documents.

The local encrypted recovery archive from the preceding milestone is older than the v4 saved-prompts schema. A current independent offsite restore has not been proven; this UI release changes data only when an administrator chooses to save page settings/content. Do not present the earlier v3 archive as a current database restoration point.

After deploying, verify the frontend text changes in both light and dark modes, live custom content CRUD, transient notifications, keyboard Escape and mobile dialog responsiveness as an authenticated admin.

Security-sensitive authentication flows remain unchanged: editable labels cannot modify credential validation, password policies, verification tokens, SMTP or access gates.
