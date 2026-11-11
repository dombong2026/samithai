<?php

namespace App\Support;

/**
 * Browser page inventory used by Appearance > Frontend Pages.
 *
 * A page appearing in Appearance does not mean its access restrictions can be
 * switched off. Only FrontendPageAvailability governs optional public routes.
 * Private pages are shown for discovery and linked to their existing manager;
 * no customer data or security settings are exposed through this registry.
 */
final class FrontendPageRegistry
{
    public static function managed(): array
    {
        return [
            [
                'key' => 'blog_detail',
                'name' => 'Blog article detail',
                'path' => '/blog/{slug}',
                'description' => 'Published blog posts and article detail template.',
                'manager' => 'posts', 'label' => 'Post Manager',
                'route_name' => 'blog.show',
            ],
            [
                'key' => 'policy_detail',
                'name' => 'Published policy detail',
                'path' => '/policies/{slug}',
                'description' => 'Published policy body, controlled by Policy Center.',
                'manager' => 'policies', 'label' => 'Policy Center',
                'route_name' => 'policies.show',
            ],
            [
                'key' => 'support_detail',
                'name' => 'Support article detail',
                'path' => '/support-center/articles/{slug}',
                'description' => 'Published knowledge-base article detail template.',
                'manager' => 'knowledge', 'label' => 'Knowledge Base',
                'route_name' => 'support.articles.show',
            ],
            [
                'key' => 'custom_template',
                'name' => 'Custom page detail template',
                'path' => '/pages/{slug}',
                'description' => 'A published custom page. Create, edit, preview and publish individual pages below.',
                'manager' => 'pages', 'label' => 'Page Manager',
                'route_name' => 'pages.show',
                'individually_published' => true,
            ],
        ];
    }

    public static function protected(): array
    {
        return [
            self::system('legal', 'Published legal license', '/legal/{kind}',
                'Versioned legal documents are controlled by Legal Review.', 'Public legal',
                'dashboard.legal', 'Manage legal', 'legal.show'),
            self::system('account_overview', 'Account overview', '/account',
                'Authenticated member home, quick launch and activity.', 'Member account',
                'account', 'Open my account'),
            self::system('account_library', 'My prompt library', '/account/library',
                'Licensed prompt entitlements, managed through verified orders.', 'Member account',
                'account.library', 'Open my library'),
            self::system('account_library_detail', 'Licensed prompt detail', '/account/library/{prompt:slug}',
                'Protected purchased content and license entitlements.', 'Member account',
                'dashboard.finance', 'Manage orders', 'account.library.show'),
            self::system('account_saved', 'Saved prompts', '/account/saved',
                'Private member bookmarks and saved prompt records.', 'Member account',
                'account.saved', 'Open saved prompts'),
            self::system('account_orders', 'Order history', '/account/orders',
                'Customer transaction history; payment and entitlement controls remain protected.', 'Member account',
                'dashboard.finance', 'Manage orders', 'account.orders'),
            self::system('order_detail', 'Order detail', '/orders/{order}',
                'Authenticated order detail and history.', 'Member account',
                'dashboard.finance', 'Manage orders', 'orders.show'),
            self::system('account_settings', 'Profile & account settings', '/account/settings',
                'Account profile updates and password/security functions.', 'Member account',
                'account.settings', 'Open my settings'),
            self::system('workspace_tasks', 'Personal tasks', '/workspace/tasks',
                'Private CRUD task manager and personal priorities.', 'Member account',
                'workspace.tasks', 'Open my tasks'),
            self::system('account_support', 'My support tickets', '/account/support',
                'Member support inbox with role-enforced ticket ownership.', 'Member account',
                'dashboard.support', 'Manage support', 'account.support'),
            self::system('support_ticket_detail', 'Support conversation', '/support/tickets/{ticket}',
                'Private ticket conversation, replies and close/reopen actions.', 'Member account',
                'dashboard.support', 'Manage support', 'support.show'),
            self::system('creator_prompts', 'Creator prompt manager', '/account/prompts',
                'Creator prompt drafts, submissions, reviews and publication workflow.', 'Creator account',
                'dashboard.prompts', 'Review prompts', 'account.prompts'),
            self::system('creator_prompt_reviews', 'Creator review history', '/account/prompts/{prompt}/reviews',
                'Private moderation feedback for the creator of each prompt.', 'Creator account',
                'dashboard.prompts', 'Review prompts', 'account.prompts.reviews'),
            self::system('creator_media', 'Creator media library', '/account/media',
                'Creator upload and media-management workspace.', 'Creator account',
                'dashboard.media', 'Manage media', 'account.media'),
            self::system('creator_insights', 'Creator insights', '/account/creator/insights',
                'Creator-specific analytics and license interest.', 'Creator account',
                'dashboard.prompts', 'Review prompts', 'account.creator.insights'),
        ];
    }

    private static function system(
        string $key,
        string $name,
        string $path,
        string $description,
        string $audience,
        string $actionRoute,
        string $actionLabel,
        ?string $pageRoute = null
    ): array {
        return [
            'key' => $key, 'name' => $name, 'path' => $path,
            'description' => $description, 'audience' => $audience,
            'action_route' => $actionRoute, 'action_label' => $actionLabel,
            'route_name' => $pageRoute ?? $actionRoute,
        ];
    }

    /**
     * Pages with known non-parameterized links are safe to preview as the
     * signed-in administrator. Dynamic/detail/auth forms may not be previewable.
     */
    public static function previewRoute(string $pageKey): ?string
    {
        return match ($pageKey) {
            'home' => 'home',
            'catalog' => 'prompts.index',
            'blog' => 'blog.index',
            'policies' => 'policies.index',
            'support' => 'support.center',
            'cart' => 'cart.index',
            default => null,
        };
    }

    /**
     * Explicit coverage manifest: the non-admin HTML GET route name must map
     * to a built-in template, managed public content or a protected account
     * page. Locale-prefixed aliases share the same content/template row.
     *
     * Installer, file streaming, verification actions and language switches
     * are operations rather than customer-facing page templates.
     */
    public static function coveredBrowserRoutes(): array
    {
        return [
            'home', 'prompts.index', 'prompts.show', 'categories.show',
            'pages.show', 'blog.index', 'blog.show', 'policies.index',
            'policies.show', 'support.center', 'support.articles.show',
            'legal.show', 'login', 'register', 'password.request',
            'password.reset', 'verification.notice', 'account',
            'account.library', 'account.library.show', 'account.saved',
            'account.creator.insights', 'account.settings',
            'account.prompts', 'account.prompts.reviews',
            'account.orders', 'orders.show', 'account.support', 'support.show',
            'account.media', 'cart.index', 'workspace.tasks',
        ];
    }
}
