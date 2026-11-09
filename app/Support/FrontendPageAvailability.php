<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * Route availability is separate from page copy and content publication.
 * Critical authentication, homepage, global shell and legal routes stay available.
 */
final class FrontendPageAvailability
{
    private const OPTIONAL = [
        'catalog' => true,
        'category' => true,
        'prompt' => true,
        'blog' => true,
        'blog_detail' => true,
        'register' => true,
        'support' => true,
        'support_detail' => true,
    ];

    public static function toggleable(string $key): bool
    {
        return isset(self::OPTIONAL[$key]);
    }

    public static function enabled(string $key): bool
    {
        if (!self::toggleable($key)) {
            return true;
        }

        return SiteSetting::text('frontend_page.enabled.'.$key, '1') !== '0';
    }
}
