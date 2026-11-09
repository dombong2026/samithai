# Samith AI premium administrator dashboard

## Customer reported defects

The former `resources/views/dashboard/overview.blade.php` had the Marketplace interest panel **after** `@endsection`; Blade emitted its HTML outside the application layout and before the workspace. The previous sidebar listed all administrator and account links as a long ungrouped vertical menu.

## New design

1. Fixed Marketplace Interest containment inside a single dashboard content section; no panel appears above the sidebar or outside `<main>`.
2. Five uniquely named admin menu groups with native keyboard-accessible `<details>`: Marketplace, Content & Media, Commerce & Support, Appearance & Localization, Platform & Security. The Overview link and account/storefront links remain outside the groups. No fake pages or missing routes.
3. Admin navigation now shows one link per destination. Personal saved prompts, library, orders, creator studio and account settings remain reachable in the profile dropdown; the customer/creator sidebar retains its own routes.
4. The sidebar scrolls independently on desktop. The mobile navigation uses a collapsible native `<details>` panel, closed initially by a small optional JavaScript enhancement and open as a fallback without JS.
5. Premium dashboard adds a hero, four live metric cards, genuine first-time category onboarding, recent prompt moderation, quick actions, marketplace interest and a real platform snapshot. No invented charts or sales/revenue values.
6. Dark/light theme support, semantic headings, route-active states, accessible buttons, and responsive two/one-column layouts.

## Scoped production deployment

Source commit: `16e39e2d15c75b8c9e33e8581cd61ab74ad88129`. Run `support/deploy-premium-admin-dashboard.sh --check` then `--apply` from a pinned script commit. Six source paths are guarded with their old and new Git blob SHAs. The script accepts all-old or all-new states, and refuses mixed/custom states without overwriting files.

On all-old state it downloads pinned sources, verifies each Git blob SHA, saves existing files into a root-only rollback directory, installs exactly the six UI files and compiles Blade. It verifies the public origin, admin guest redirect and CSS/JS assets. On any failure after installation it attempts to restore original files and Blade cache. On all-new state it performs verification without recopying.

This deployment does NOT run SQL migrations, change `.env`, Nginx, payments, private media or customer data. The existing AES-256 encrypted v3 backup remains local and unrecovered offsite. Authenticated administrator interactions and physical-device acceptance require separate testing.
