<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * Editable copy for the application's built-in public-page templates.
 * Content records, legal versions and security/payment rules are managed elsewhere.
 */
final class FrontendPageCopy
{
    private const DEFINITIONS = [
        'shell' => [
            'name' => 'Global header & footer',
            'path' => 'All public pages',
            'description' => 'Common storefront labels. Brand and theme controls remain in Appearance.',
            'fields' => [
                'top_strip' => ['label' => 'Top bar label', 'default' => 'Samith AI Marketplace'],
                'search_placeholder' => ['label' => 'Header search placeholder', 'default' => 'Search prompts'],
            ],
        ],
        'home' => [
            'name' => 'Homepage',
            'path' => '/',
            'description' => 'Hero calls-to-action and the category and featured prompt sections.',
            'fields' => [
                'eyebrow' => ['label' => 'Hero eyebrow', 'default' => 'Samith AI · Prompt marketplace'],
                'primary_cta' => ['label' => 'Primary action label', 'default' => 'Browse prompts'],
                'guest_cta' => ['label' => 'Guest action label', 'default' => 'Create account'],
                'member_cta' => ['label' => 'Signed-in action label', 'default' => 'My workspace'],
                'categories_title' => ['label' => 'Categories heading', 'default' => 'Explore categories'],
                'categories_cta' => ['label' => 'Categories action label', 'default' => 'All prompts'],
                'categories_empty' => ['label' => 'Empty categories message', 'default' => 'Categories will appear when published by an administrator.', 'type' => 'textarea'],
                'recent_eyebrow' => ['label' => 'Featured section eyebrow', 'default' => 'Marketplace'],
                'recent_title' => ['label' => 'Featured section heading', 'default' => 'Recently published prompts'],
                'recent_cta' => ['label' => 'Featured section action', 'default' => 'Explore all'],
                'recent_empty_title' => ['label' => 'Empty prompts heading', 'default' => 'No published prompts yet'],
                'recent_empty_message' => ['label' => 'Empty prompts message', 'default' => 'Approved creator prompts will appear here.', 'type' => 'textarea'],
                'show_categories' => ['label' => 'Show categories section', 'default' => true, 'type' => 'toggle'],
                'show_recent' => ['label' => 'Show recent prompts section', 'default' => true, 'type' => 'toggle'],
                'show_hero_art' => ['label' => 'Show hero decoration', 'default' => true, 'type' => 'toggle'],
            ],
        ],
        'cart' => [
            'name' => 'Shopping cart',
            'path' => '/cart',
            'description' => 'Authenticated cart presentation; checkout, payments and legal acceptance remain protected.',
            'fields' => [
                'title' => ['label' => 'Page heading', 'default' => 'Shopping cart'],
                'subtitle' => ['label' => 'Page description', 'default' => 'Review your selected AI prompts.', 'type' => 'textarea'],
                'empty_title' => ['label' => 'Empty cart heading', 'default' => 'Your cart is empty'],
                'empty_message' => ['label' => 'Empty cart description', 'default' => 'Browse the marketplace to find useful prompts.', 'type' => 'textarea'],
                'browse_cta' => ['label' => 'Browse prompts link', 'default' => 'Browse prompts'],
                'remove_cta' => ['label' => 'Remove item action', 'default' => 'Remove'],
            ],
        ],
        'catalog' => [
            'name' => 'Browse prompts',
            'path' => '/prompts',
            'description' => 'Catalog headings, search controls and empty results.',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'default' => 'Discover'],
                'title' => ['label' => 'Page heading', 'default' => 'AI prompt marketplace'],
                'description' => ['label' => 'Page description', 'default' => 'Search and explore prompts approved by the marketplace team.', 'type' => 'textarea'],
                'search_cta' => ['label' => 'Search button', 'default' => 'Search & filter'],
                'clear_cta' => ['label' => 'Clear button', 'default' => 'Clear'],
                'empty_title' => ['label' => 'Empty results title', 'default' => 'No matching prompts'],
                'empty_message' => ['label' => 'Empty results message', 'default' => 'Try a different keyword or filter.', 'type' => 'textarea'],
                'show_filters' => ['label' => 'Show catalog filter bar', 'default' => true, 'type' => 'toggle'],
            ],
        ],
        'category' => [
            'name' => 'Category listing',
            'path' => '/categories/{slug}',
            'description' => 'Shared category template; category names and descriptions use Categories Manager.',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'default' => 'Category'],
                'all_cta' => ['label' => 'Browse all label', 'default' => 'Browse all prompts'],
                'empty_message' => ['label' => 'Empty category message', 'default' => 'No published prompts in this category', 'type' => 'textarea'],
            ],
        ],
        'prompt' => [
            'name' => 'Prompt details',
            'path' => '/prompts/{slug}',
            'description' => 'Listing action labels. Licensed content and legal notices are protected.',
            'fields' => [
                'back_cta' => ['label' => 'Back button', 'default' => '← All prompts'],
                'creator_label' => ['label' => 'Creator caption', 'default' => 'Creator'],
                'cart_cta' => ['label' => 'Add to cart label', 'default' => 'Add to cart'],
                'saved_cta' => ['label' => 'Save prompt label', 'default' => 'Save for later'],
                'saved_remove_cta' => ['label' => 'Remove saved label', 'default' => 'Saved — remove'],
                'saved_list_cta' => ['label' => 'My saved prompts label', 'default' => 'My saved prompts'],
            ],
        ],
        'blog' => [
            'name' => 'Blog listing',
            'path' => '/blog',
            'description' => 'Blog index; individual posts are managed in Post Manager.',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'default' => 'Updates'],
                'title' => ['label' => 'Page heading', 'default' => 'Samith AI Blog'],
                'article_cta' => ['label' => 'Article link', 'default' => 'Read article →'],
                'empty_message' => ['label' => 'Empty blog message', 'default' => 'No published articles are available yet.', 'type' => 'textarea'],
            ],
        ],
        'policies' => [
            'name' => 'Policies listing',
            'path' => '/policies',
            'description' => 'Presentation labels only; legal policy bodies remain managed in Policy Center.',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'default' => 'Legal'],
                'title' => ['label' => 'Page heading', 'default' => 'Marketplace policies'],
                'description' => ['label' => 'Page description', 'default' => 'Review the published legal and marketplace policies. These terms are managed by the administrator.', 'type' => 'textarea'],
                'policy_cta' => ['label' => 'Policy link', 'default' => 'View policy →'],
                'empty_message' => ['label' => 'Empty policies message', 'default' => 'Marketplace policies have not been published yet.', 'type' => 'textarea'],
            ],
        ],
        'login' => [
            'name' => 'Sign in',
            'path' => '/login',
            'description' => 'Guest login page labels; authentication rules remain protected.',
            'fields' => [
                'title' => ['label' => 'Heading', 'default' => 'Welcome back'],
                'subtitle' => ['label' => 'Description', 'default' => 'Sign in to manage your prompts and purchases.', 'type' => 'textarea'],
                'submit' => ['label' => 'Sign-in button', 'default' => 'Sign in'],
                'bottom' => ['label' => 'Bottom prompt', 'default' => 'New to Samith AI?'],
                'bottom_cta' => ['label' => 'Registration link label', 'default' => 'Create account'],
            ],
        ],
        'register' => [
            'name' => 'Registration',
            'path' => '/register',
            'description' => 'Account registration copy; validation, passwords and email requirements are unchanged.',
            'fields' => [
                'title' => ['label' => 'Heading', 'default' => 'Create an account'],
                'subtitle' => ['label' => 'Description', 'default' => 'Start your AI prompt collection.', 'type' => 'textarea'],
                'submit' => ['label' => 'Registration button', 'default' => 'Create account'],
                'bottom' => ['label' => 'Bottom prompt', 'default' => 'Already registered?'],
                'bottom_cta' => ['label' => 'Sign-in link', 'default' => 'Sign in'],
            ],
        ],
        'forgot' => [
            'name' => 'Password recovery',
            'path' => '/forgot-password',
            'description' => 'Password reset request copy; secure recovery and throttling are unchanged.',
            'fields' => [
                'title' => ['label' => 'Heading', 'default' => 'Reset your password'],
                'subtitle' => ['label' => 'Description', 'default' => 'Enter your account email address to request a secure reset link.', 'type' => 'textarea'],
                'submit' => ['label' => 'Request button', 'default' => 'Request reset link'],
                'back' => ['label' => 'Back link', 'default' => 'Back to sign in'],
            ],
        ],
        'reset' => [
            'name' => 'Set new password',
            'path' => '/reset-password/{token}',
            'description' => 'Password form copy; minimum password policy and token verification are protected.',
            'fields' => [
                'title' => ['label' => 'Heading', 'default' => 'Choose a new password'],
                'subtitle' => ['label' => 'Description', 'default' => 'Your new password must contain at least 12 characters, including letters and numbers.', 'type' => 'textarea'],
                'submit' => ['label' => 'Change password button', 'default' => 'Update password'],
                'back' => ['label' => 'Back link', 'default' => 'Back to sign in'],
            ],
        ],
        'verify' => [
            'name' => 'Email verification',
            'path' => '/email/verify',
            'description' => 'Account verification headings and action labels; SMTP/security logic stays untouched.',
            'fields' => [
                'title' => ['label' => 'Page heading', 'default' => 'Verify your email'],
                'subtitle' => ['label' => 'Description', 'default' => 'Confirm that you own your account email address.', 'type' => 'textarea'],
                'settings_cta' => ['label' => 'Settings link', 'default' => 'Account settings'],
                'resend_cta' => ['label' => 'Resend link button', 'default' => 'Resend verification email'],
            ],
        ],
        'support' => [
            'name' => 'Support Center',
            'path' => '/support-center',
            'description' => 'Help landing copy. Support tickets and article content use their existing managers.',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'default' => 'Help & guidance'],
                'title' => ['label' => 'Page heading', 'default' => 'Support Center'],
                'description' => ['label' => 'Page description', 'default' => 'Browse help articles or contact our team from your account.', 'type' => 'textarea'],
                'member_cta' => ['label' => 'Member action', 'default' => 'My support tickets'],
                'guest_cta' => ['label' => 'Guest action', 'default' => 'Sign in for support'],
                'articles_title' => ['label' => 'Articles heading', 'default' => 'Knowledge base'],
                'article_cta' => ['label' => 'Article link', 'default' => 'Read article →'],
                'empty_message' => ['label' => 'Empty articles message', 'default' => 'Knowledge base articles will appear when published.', 'type' => 'textarea'],
            ],
        ],
    ];

    public static function definitions(): array
    {
        return self::DEFINITIONS;
    }

    public static function definition(string $key): ?array
    {
        return self::DEFINITIONS[$key] ?? null;
    }

    public static function values(string $key): array
    {
        $definition = self::definition($key);
        if (!$definition) return [];

        $stored = json_decode(SiteSetting::text('frontend_copy.'.$key), true);
        if (!is_array($stored)) $stored = [];

        $values = [];
        foreach ($definition['fields'] as $field => $config) {
            $value = $stored[$field] ?? $config['default'];
            $values[$field] = ($config['type'] ?? '') === 'toggle'
                ? (is_bool($value) ? $value : (bool) $config['default'])
                : (is_string($value) ? $value : $config['default']);
        }
        return $values;
    }

    public static function hasCustomCopy(string $key): bool
    {
        return SiteSetting::query()->whereKey('frontend_copy.'.$key)->exists();
    }
}
