<?php
/**
 * Synthetic staging data for the private, localhost-only SEO/editorial release.
 * NEVER use this with the live database or an existing staging dataset.
 */
declare(strict_types=1);

use App\Models\ContentEntry;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\SafeHtml;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

$expectedRoot = '/var/lib/samithai-editorial-staging-20261112';
$expectedDb = $expectedRoot.'/shared/staging.sqlite';
$root = realpath(__DIR__.'/..');

try {
    if (!in_array($root, [
        $expectedRoot.'/releases/candidate',
        $expectedRoot.'/releases/rollback',
    ], true)) {
        throw new RuntimeException('STAGING_SOURCE_DIRECTORY_MISMATCH');
    }

    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    if (config('app.env') !== 'staging'
        || config('database.default') !== 'sqlite'
        || DB::connection()->getDatabaseName() !== $expectedDb
        || config('cache.default') !== 'file'
        || config('session.driver') !== 'file'
        || config('queue.default') !== 'sync'
        || config('mail.default') !== 'array'
        || config('app.debug') !== false) {
        throw new RuntimeException('STAGING_RUNTIME_GUARD_FAILED');
    }

    if (User::query()->exists() || ContentEntry::query()->exists()
        || MediaAsset::query()->exists()) {
        throw new RuntimeException('STAGING_DATABASE_NOT_EMPTY');
    }

    $webp = base64_decode(
        'UklGRkQAAABXRUJQVlA4IDgAAAAQAwCdASogABIAPm00lkekIyIhKAgAgA2JZQB2AACAoKAA/u4KZ//cGZ9XVM///FodaHWhnSAAAA==',
        true
    );
    if (!$webp || !str_starts_with($webp, 'RIFF')) {
        throw new RuntimeException('SYNTHETIC_IMAGE_FIXTURE_INVALID');
    }

    $mapping = [
        '20-practical-ai-prompts-for-small-businesses' => '01_small-business-ai-prompts',
        'how-teachers-can-use-ai-to-prepare-lesson-plans' => '02_teachers-ai-lesson-plans',
        'how-to-create-a-professional-website-using-ai' => '03_ai-website-from-brief-to-launch',
        '15-ai-prompts-for-social-media-marketing' => '04_social-media-ai-prompts',
        'how-to-test-and-improve-an-ai-prompt' => '05_ai-prompt-quality-checklist',
        'ai-prompt-engineering-vs-ai-agents' => '06_prompt-engineering-vs-ai-agents',
    ];

    $bundles = [
        '01-fundamentals.json',
        '02-business-and-teaching.json',
        '03-website-and-social.json',
        '04-quality-and-agents.json',
    ];

    $records = [];
    foreach ($bundles as $file) {
        $path = $root.'/database/content/educational-blog-2026-11/'.$file;
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('SOURCE_FILE_MISSING:'.$file);
        }
        $json = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (($json['schema'] ?? null) !== 'samithai.blog-drafts.v1'
            || count($json['articles'] ?? []) !== 2) {
            throw new RuntimeException('INVALID_SOURCE_BUNDLE:'.$file);
        }
        foreach ($json['articles'] as $item) {
            if (($item['status'] ?? null) !== 'draft'
                || isset($records[$item['slug']])) {
                throw new RuntimeException('SOURCE_RECORD_UNSAFE:'.$file);
            }
            $records[$item['slug']] = $item;
        }
    }
    if (count($records) !== 8) {
        throw new RuntimeException('EIGHT_SOURCE_ARTICLES_REQUIRED');
    }

    $credentials = DB::transaction(function () use ($mapping, $records, $webp): array {
        $adminPassword = bin2hex(random_bytes(24));
        $customerPassword = bin2hex(random_bytes(24));

        $admin = User::query()->create([
            'name' => 'Staging Administrator',
            'email' => 'editorial-admin@staging.invalid',
            'password' => $adminPassword,
        ]);
        $admin->forceFill(['role' => 'admin', 'email_verified_at' => now()])->save();

        $customer = User::query()->create([
            'name' => 'Staging Customer',
            'email' => 'editorial-customer@staging.invalid',
            'password' => $customerPassword,
        ]);
        $customer->forceFill(['role' => 'customer', 'email_verified_at' => now()])->save();

        $count = 0;
        foreach ($records as $slug => $data) {
            $draft = array_key_exists($slug, $mapping);
            $media = null;

            if ($draft) {
                $title = $mapping[$slug];
                $path = 'media/staging/'.$title.'.webp';
                if (!Storage::disk('local')->put($path, $webp)) {
                    throw new RuntimeException('SYNTHETIC_MEDIA_WRITE_FAILED');
                }
                $media = MediaAsset::query()->create([
                    'user_id' => $admin->id,
                    'kind' => 'image',
                    'title' => $title,
                    'mime' => 'image/webp',
                    'file_path' => $path,
                    'bytes' => strlen($webp),
                    'checksum' => hash('sha256', $webp),
                ]);
            }

            $post = ContentEntry::query()->create([
                'user_id' => $admin->id,
                'kind' => 'post',
                'title' => $data['title'],
                'slug' => $slug,
                'excerpt' => $data['excerpt'],
                'body_html' => SafeHtml::clean($data['body_html']),
                'featured_media_id' => $media?->id,
                'status' => $draft ? 'draft' : 'published',
                'editorial_status' => 'draft',
                'published_at' => $draft ? null : now(),
            ]);
            $post->seo()->create([
                'seo_title' => $data['seo_title'],
                'meta_description' => $data['meta_description'],
                'og_title' => $data['seo_title'],
                'og_description' => $data['meta_description'],
                'robots_index' => true,
                'structured_data_enabled' => true,
            ]);
            ++$count;
        }

        if ($count !== 8) throw new RuntimeException('SOURCE_SEED_COUNT_MISMATCH');

        return [
            'admin_email' => $admin->email,
            'admin_password' => $adminPassword,
            'customer_email' => $customer->email,
            'customer_password' => $customerPassword,
            'classification' => 'DISPOSABLE_SYNTHETIC_STAGING_ONLY',
        ];
    });

    $destination = $expectedRoot.'/shared/storage/app/staging-credentials.json';
    if (file_exists($destination) || is_link($destination)) {
        throw new RuntimeException('CREDENTIALS_DESTINATION_EXISTS');
    }
    if (file_put_contents($destination, json_encode($credentials, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
        throw new RuntimeException('STAGING_CREDENTIALS_WRITE_FAILED');
    }
    chmod($destination, 0600);

    echo "SYNTHETIC_EDUCATIONAL_ARTICLES=8\n";
    echo "SYNTHETIC_PUBLISHED_STARTER_ARTICLES=2\n";
    echo "SIX_ARTICLES_STILL_DRAFT=YES\n";
    echo "SYNTHETIC_FEATURED_WEBP_ASSETS=6\n";
    echo "SOCIAL_IMAGE_DEFAULTS=FEATURED_FALLBACK\n";
    echo "STAGING_CREDENTIALS_FILE=".$destination."\n";
    echo "PRODUCTION_DATABASE_AND_MEDIA=NOT_ACCESSED\n";
} catch (Throwable $e) {
    fwrite(STDERR, "SYNTHETIC_SEED_FAILED=".get_class($e).":".$e->getMessage()."\n");
    exit(3);
}
