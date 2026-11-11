<?php

namespace App\Support;

use App\Models\User;
use App\Models\WorkspacePreference;

final class WorkspaceLaunchpad
{
    public const MAX_PINNED = 6;

    /**
     * Shortcuts resolve only to fixed existing routes. A submitted value is never a URL.
     */
    public static function catalog(User $user, string $context): array
    {
        abort_unless(in_array($context, ['admin', 'account'], true), 404);
        if ($context === 'admin') {
            abort_unless($user->isAdmin(), 403);
            return [
                'moderation' => ['label' => 'Moderation queue', 'description' => 'Review submitted prompts', 'icon' => 'review', 'url' => route('dashboard.prompts', ['status' => 'pending'])],
                'prompt_qa' => ['label' => 'Prompt Quality Center', 'description' => 'Test starter prompt drafts', 'icon' => 'check', 'url' => route('dashboard.prompt-quality')],
                'tasks' => ['label' => 'My tasks', 'description' => 'Manage personal priorities', 'icon' => 'list-todo', 'url' => route('workspace.tasks')],
                'support' => ['label' => 'Support inbox', 'description' => 'Reply to customer tickets', 'icon' => 'support', 'url' => route('dashboard.support')],
                'content' => ['label' => 'Content Manager', 'description' => 'Posts, pages and drafts', 'icon' => 'posts', 'url' => route('dashboard.content', 'posts')],
                'users' => ['label' => 'Users & roles', 'description' => 'Manage account access', 'icon' => 'users', 'url' => route('dashboard.users')],
                'categories' => ['label' => 'Categories', 'description' => 'Organize the catalog', 'icon' => 'categories', 'url' => route('dashboard.categories')],
                'media' => ['label' => 'Media Library', 'description' => 'Manage uploads', 'icon' => 'media', 'url' => route('dashboard.media')],
                'appearance' => ['label' => 'Appearance', 'description' => 'Frontend page controls', 'icon' => 'appearance', 'url' => route('dashboard.appearance')],
                'finance' => ['label' => 'Finance', 'description' => 'Review order records', 'icon' => 'finance', 'url' => route('dashboard.finance')],
                'security' => ['label' => 'Security Center', 'description' => 'Review safeguards', 'icon' => 'security', 'url' => route('dashboard.security')],
                'languages' => ['label' => 'Language Manager', 'description' => 'Localization settings', 'icon' => 'language', 'url' => route('dashboard.languages')],
            ];
        }

        $items = [
            'tasks' => ['label' => 'My tasks', 'description' => 'Manage personal priorities', 'icon' => 'list-todo', 'url' => route('workspace.tasks')],
            'library' => ['label' => 'My library', 'description' => 'Licensed prompt collection', 'icon' => 'library', 'url' => route('account.library')],
            'saved' => ['label' => 'Saved prompts', 'description' => 'Your favorites', 'icon' => 'saved', 'url' => route('account.saved')],
            'orders' => ['label' => 'Order history', 'description' => 'Your order records', 'icon' => 'finance', 'url' => route('account.orders')],
            'cart' => ['label' => 'My cart', 'description' => 'Review cart items', 'icon' => 'cart', 'url' => route('cart.index')],
            'tickets' => ['label' => 'Support tickets', 'description' => 'Your conversations', 'icon' => 'support', 'url' => route('account.support')],
            'settings' => ['label' => 'Account settings', 'description' => 'Profile and security', 'icon' => 'account', 'url' => route('account.settings')],
        ];
        if ($user->isCreator()) {
            $items += [
                'prompts' => ['label' => 'My prompts', 'description' => 'Drafts and submissions', 'icon' => 'posts', 'url' => route('account.prompts')],
                'prompt_qa' => ['label' => 'Prompt Quality Center', 'description' => 'Track model test results', 'icon' => 'check', 'url' => route('account.prompt-quality')],
                'media' => ['label' => 'My media', 'description' => 'Creator uploads', 'icon' => 'media', 'url' => route('account.media')],
                'insights' => ['label' => 'Creator insights', 'description' => 'Real marketplace interest', 'icon' => 'insights', 'url' => route('account.creator.insights')],
            ];
        }
        if (FrontendPageAvailability::enabled('catalog')) {
            $items['marketplace'] = ['label' => 'Marketplace', 'description' => 'Browse published prompts', 'icon' => 'store', 'url' => route('prompts.index')];
        }
        return $items;
    }

    public static function data(User $user, string $context): array
    {
        $catalog = self::catalog($user, $context);
        $defaults = $context === 'admin'
            ? ['moderation', 'support', 'content', 'users']
            : ($user->isCreator() ? ['prompts', 'insights', 'library', 'tickets'] : ['library', 'saved', 'tickets', 'settings']);
        $saved = WorkspacePreference::query()
            ->where('user_id', $user->getKey())->where('context', $context)->first();
        $requested = $saved ? (array) $saved->shortcuts : $defaults;
        $keys = array_values(array_filter($requested, fn ($key) => is_string($key) && isset($catalog[$key])));
        $keys = array_slice(array_unique($keys), 0, self::MAX_PINNED);
        if ($keys === []) {
            $keys = array_values(array_filter($defaults, fn ($key) => isset($catalog[$key])));
        }
        return ['context' => $context, 'catalog' => $catalog, 'keys' => $keys];
    }
}
