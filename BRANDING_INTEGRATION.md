# Samith AI favicon and logo integration

The generated orange S favicon and horizontal Samith AI logo have been vector-traced into local SVG assets to display crisply at small sizes.
The light logo has navy Samith text; the dark logo has white text; both preserve the orange AI accent.

Assets: /favicon.svg, /branding/samith-ai-logo-light.svg, and /branding/samith-ai-logo-dark.svg.
Blade partials provide the favicon and the wordmark across storefront header/footer, workspace, login, registration, password recovery, and installer.
Default dimensions stay 143 x 35; theme switching uses the existing HTML data-theme attribute.
An already configured uploaded custom logo continues to take precedence.

The operator-only support/deploy-branding.sh script is limited to the exact 13 branding and Blade/CSS files.
Run --check before --apply on the actual aaPanel production host. The patch verifies current source hashes, downloads source from a pinned commit, checks every target hash, preserves private backups, and rolls back on failed checks.
No database, Nginx, authentication route, .env, or payment configuration changes.
An SVG favicon works in modern browsers; older browser .ico fallback and iOS-specific icons may be added separately.
