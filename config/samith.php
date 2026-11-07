<?php

return [
    // Explicitly false until real outbound mail delivery and DNS identity are tested.
    'email_delivery_enabled' => (bool) env('SAMITH_EMAIL_DELIVERY_ENABLED', false),
    'require_verified_email' => (bool) env('SAMITH_REQUIRE_VERIFIED_EMAIL', false),
    'locales' => [
        'en' => 'English', 'zh' => '中文', 'ko' => '한국어', 'ja' => '日本語',
        'de' => 'Deutsch', 'km' => 'ខ្មែរ', 'vi' => 'Tiếng Việt', 'th' => 'ไทย',
        'id' => 'Bahasa Indonesia', 'ms' => 'Bahasa Melayu', 'ar' => 'العربية',
        'fr' => 'Français', 'es' => 'Español',
    ],
    // Cloudflare country headers are advisory; only trust them if configured behind a trusted proxy.
    'trust_cloudflare_country' => (bool) env('SAMITH_TRUST_CF_COUNTRY', false),
    'country_locale' => [
        'KH'=>'km', 'CN'=>'zh', 'TW'=>'zh', 'KR'=>'ko', 'JP'=>'ja',
        'DE'=>'de', 'AT'=>'de', 'VN'=>'vi', 'TH'=>'th', 'ID'=>'id',
        'MY'=>'ms', 'SA'=>'ar', 'AE'=>'ar', 'FR'=>'fr', 'ES'=>'es',
    ],
];
