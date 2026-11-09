# Samith AI — Premium User Account & Creator Workspace

## Scope

The Admin Dashboard is preserved. The previously flat user/creator sidebar is reorganized into collapsible native groups with one unique destination per sidebar item. All links point to existing Laravel routes; no fake pages, no new database tables, and no additional permissions.

### Customer navigation

- **Account overview** (standalone).
- **Library & Saved:** My library; Saved prompts.
- **Purchases:** My orders; My cart.
- **Help & Settings:** Support tickets; Account settings.
- **Explore marketplace** as a dedicated external-workspace shortcut.

### Creator navigation

- **Creator overview** (standalone).
- **Creator Studio:** My prompts; My media; Creator insights.
- **Library & Saved**, **Purchases**, and **Help & Settings**, same as customers.
- **Explore marketplace** as the storefront shortcut.

### Interface and behavior

- Native `<details>` sections, SVG icons, active-route highlighting, creator-only item visibility and responsive/keyboard-friendly navigation.
- The open group follows the current page. On the account overview the creator group or member collection group opens by default.
- Mobile retains the compact nav trigger; on desktop the sidebar scrolls independently.
- The account overview now presents live metrics, clearer library/purchase functions, a creator-only workspace panel, and an invitation for customers to become creators using the existing verified-email-gated route. Paid checkout remains disabled.
- Admin route groups remain untouched and customer/creator security is unchanged.

### Deployment

Deploy only the three reviewed files: `resources/views/layouts/workspace.blade.php`, `resources/views/account/index.blade.php` and `public/assets/app.css`. Use the pinned `support/deploy-account-creator-navigation.sh --check` and `--apply` script. It requires all-old or all-new Git blobs, refuses drift/mixed states, creates a root-only rollback copy of the existing three files, compiles views, checks existing guest redirects and verifies CSS.

No SQL migration or backup restore occurs. The previous encrypted local recovery snapshot is older than the current saved-prompts v4 schema, so it is not a current database restoration point; this UI-only patch does not modify database records. Keep the rollback code copy and test with signed-in customer, creator and admin sessions.
