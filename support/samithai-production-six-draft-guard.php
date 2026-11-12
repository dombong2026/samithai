<?php

/**
 * Read-only production fingerprint for the six protected educational drafts.
 *
 * Usage: php support/samithai-production-six-draft-guard.php /absolute/live/release
 *
 * Does not depend on the future SEO schema. Never approves, publishes, or
 * changes a record. Safe to run before and after an approved release.
 */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

$EXPECTED = [
    '20-practical-ai-prompts-for-small-businesses' => [
        '20 Practical AI Prompts for Small Businesses', '01_small-business-ai-prompts',
    ],
    'how-teachers-can-use-ai-to-prepare-lesson-plans' => [
        'How Teachers Can Use AI to Prepare Better Lesson Plans', '02_teachers-ai-lesson-plans',
    ],
    'how-to-create-a-professional-website-using-ai' => [
        'How to Create a Professional Website Using AI: From Brief to Launch',
        '03_ai-website-from-brief-to-launch',
    ],
    '15-ai-prompts-for-social-media-marketing' => [
        '15 AI Prompts for Social Media Marketing That You Can Adapt',
        '04_social-media-ai-prompts',
    ],
    'how-to-test-and-improve-an-ai-prompt' => [
        'How to Test and Improve an AI Prompt: A Practical Quality Checklist',
        '05_ai-prompt-quality-checklist',
    ],
    'ai-prompt-engineering-vs-ai-agents' => [
        'AI Prompt Engineering vs. AI Agents: What’s the Difference?',
        '06_prompt-engineering-vs-ai-agents',
    ],
];

try {
    $root = $argv[1] ?? '';
    if (!is_string($root) || !str_starts_with($root, '/')) {
        throw new RuntimeException('EXPECTED_ABSOLUTE_RELEASE_PATH');
    }
    $canonical = realpath($root);
    if (!$canonical || !str_starts_with($canonical, '/www/wwwroot/samithai-releases/')) {
        throw new RuntimeException('OUTSIDE_APPROVED_PRODUCTION_RELEASES');
    }
    if (!is_file($canonical.'/artisan') ||
        !is_file($canonical.'/vendor/autoload.php') ||
        !is_file($canonical.'/.env')) {
        throw new RuntimeException('INVALID_LARAVEL_RELEASE');
    }

    require $canonical.'/vendor/autoload.php';
    $app = require $canonical.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    if (config('app.env') !== 'production'
        || config('app.url') !== 'https://samithai.com'
        || DB::connection()->getDriverName() !== 'mysql'
        || (string) config('filesystems.default') !== 'local') {
        throw new RuntimeException('NON_PRODUCTION_IDENTITY_OR_UNEXPECTED_DATABASE');
    }

    $disk = Storage::disk('local');
    $fingerprints = [];
    foreach ($EXPECTED as $slug => [$title, $mediaTitle]) {
        $rows = DB::table('content_entries')
            ->where('slug', $slug)->where('kind', 'post')->get();
        if ($rows->count() !== 1 ||
            $rows[0]->title !== $title ||
            $rows[0]->status !== 'draft' ||
            !$rows[0]->featured_media_id) {
            throw new RuntimeException('PROTECTED_ARTICLE_NOT_EXACT_DRAFT:'.$slug);
        }

        $post = $rows[0];
        $assets = DB::table('media_assets')
            ->where('id', $post->featured_media_id)->get();
        if ($assets->count() !== 1) {
            throw new RuntimeException('FEATURED_IMAGE_REFERENCE_NOT_FOUND:'.$slug);
        }
        $media = $assets[0];
        if ($media->kind !== 'image' ||
            $media->title !== $mediaTitle ||
            $media->mime !== 'image/webp' ||
            !is_string($media->file_path) ||
            !str_starts_with($media->file_path, 'media/') ||
            !$disk->exists($media->file_path)) {
            throw new RuntimeException('FEATURED_WEBP_MISSING_OR_UNEXPECTED:'.$slug);
        }
        $path = $disk->path($media->file_path);
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('UNSAFE_OR_MISSING_MEDIA_FILE:'.$slug);
        }
        $checksum = hash_file('sha256', $path);
        if (!empty($media->checksum) &&
            !hash_equals((string) $media->checksum, $checksum)) {
            throw new RuntimeException('MEDIA_CHECKSUM_MISMATCH:'.$slug);
        }

        $fingerprints[] = [
            'slug' => $slug,
            'post_id' => (int) $post->id,
            'status' => (string) $post->status,
            'featured_media_id' => (int) $media->id,
            'media_sha256' => $checksum,
            'body_sha256' => hash('sha256', (string) $post->body_html),
            'seo_meta_id' => DB::getSchemaBuilder()->hasTable('seo_metadata')
                ? DB::table('seo_metadata')->where('content_entry_id', $post->id)->value('id')
                : null,
        ];
    }

    $hashPayload = [];
    foreach ($fingerprints as $item) {
        // Explicitly exclude schema-dependent columns, timestamps, and IDs
        // that migrations may assign to new SEO metadata.
        $hashPayload[] = [
            $item['slug'],
            $item['post_id'],
            $item['status'],
            $item['featured_media_id'],
            $item['media_sha256'],
            $item['body_sha256'],
        ];
    }

    echo json_encode([
        'format' => 'samithai/six-article-fingerprint/v1',
        'mode' => 'READ_ONLY',
        'all_six_articles_remain_drafts' => true,
        'featured_media_files' => '6_OF_6_VERIFIED',
        'fingerprint_sha256' => hash('sha256',
            json_encode($hashPayload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
        'seo_schema_installed' => DB::getSchemaBuilder()->hasTable('seo_metadata'),
        'articles' => $fingerprints,
        'production_database_changed' => false,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

} catch (Throwable $e) {
    fwrite(STDERR, 'PRODUCTION_FINGERPRINT_BLOCKED='.
        preg_replace('/[^A-Za-z0-9:_-]/', '_', $e->getMessage())."\n");
    exit(3);
}
