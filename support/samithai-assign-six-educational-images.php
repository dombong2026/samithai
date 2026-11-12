<?php

/**
 * One-time six-article featured image assignment.
 *
 * Usage:
 *   php support/samithai-assign-six-educational-images.php --plan  /absolute/live/app
 *   php support/samithai-assign-six-educational-images.php --apply /absolute/live/app
 *
 * Execute as the web application user. The --plan path is read-only.
 * --apply is all-or-nothing and never publishes content.
 */
declare(strict_types=1);

use App\Models\ContentEntry;
use App\Models\MediaAsset;
use App\Support\EditorialGate;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

$mode = $argv[1] ?? '';
$root = $argv[2] ?? '';
if (! in_array($mode, ['--plan', '--apply'], true)
    || ! str_starts_with($root, '/')
    || ! is_file($root.'/artisan')
    || ! is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "Usage: php script.php --plan|--apply /absolute/live/app\n");
    exit(2);
}

$mapping = [
    '20 Practical AI Prompts for Small Businesses'
        => '01_small-business-ai-prompts',
    'How Teachers Can Use AI to Prepare Better Lesson Plans'
        => '02_teachers-ai-lesson-plans',
    'How to Create a Professional Website Using AI: From Brief to Launch'
        => '03_ai-website-from-brief-to-launch',
    '15 AI Prompts for Social Media Marketing That You Can Adapt'
        => '04_social-media-ai-prompts',
    'How to Test and Improve an AI Prompt: A Practical Quality Checklist'
        => '05_ai-prompt-quality-checklist',
    'AI Prompt Engineering vs. AI Agents: What’s the Difference?'
        => '06_prompt-engineering-vs-ai-agents',
];

try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    if (config('app.env') !== 'production'
        || DB::connection()->getDriverName() !== 'mysql'
        || preg_match('/(^|[_-])(qa|test|staging)([_-]|$)/i', (string) DB::connection()->getDatabaseName())) {
        throw new RuntimeException('Blocked: this operation requires the live production MySQL application.');
    }

    foreach (['content_entries', 'media_assets'] as $table) {
        if (! Schema::hasTable($table)) {
            throw new RuntimeException('Missing required table: '.$table);
        }
    }
    if (! Schema::hasColumn('content_entries', 'featured_media_id')) {
        throw new RuntimeException('Live content does not support featured_media_id.');
    }

    $reviewCols = [
        'editorial_status', 'editorial_approved_at',
        'editorial_approved_hash', 'scheduled_for',
    ];
    $reviewFound = array_filter(
        $reviewCols,
        fn (string $name): bool => Schema::hasColumn('content_entries', $name)
    );
    if (count($reviewFound) !== 0 && count($reviewFound) !== count($reviewCols)) {
        throw new RuntimeException('Partial editorial schema; refuse to modify publication data.');
    }
    $hasReview = count($reviewFound) === count($reviewCols);
    $hasSeo = Schema::hasTable('seo_metadata')
        && Schema::hasColumn('seo_metadata', 'og_media_id')
        && Schema::hasColumn('seo_metadata', 'content_entry_id');
    $hasRevisions = $hasReview
        && Schema::hasTable('content_editorial_revisions')
        && class_exists(EditorialGate::class);

    $disk = Storage::disk('local');
    $items = [];

    foreach ($mapping as $expectedTitle => $mediaTitle) {
        $posts = DB::table('content_entries')
            ->where('kind', 'post')
            ->where('title', $expectedTitle)
            ->get();

        if ($posts->count() !== 1 || $posts[0]->title !== $expectedTitle) {
            $available = DB::table('content_entries')
                ->where('kind', 'post')
                ->where('status', 'draft')
                ->orderBy('id')
                ->pluck('title')
                ->all();
            throw new RuntimeException(
                'Could not uniquely match draft article: '.$expectedTitle.
                "\nExisting draft titles:\n - ".implode("\n - ", $available)
            );
        }
        $post = $posts[0];

        if ($post->status !== 'draft') {
            throw new RuntimeException('Refusing to edit a published article: '.$expectedTitle);
        }

        $assets = DB::table('media_assets')
            ->where('kind', 'image')
            ->where('title', $mediaTitle)
            ->get();
        if ($assets->count() !== 1 || $assets[0]->title !== $mediaTitle) {
            throw new RuntimeException('Missing or ambiguous Media Library image: '.$mediaTitle);
        }
        $asset = $assets[0];
        if ($asset->mime !== 'image/webp'
            || ! is_string($asset->file_path)
            || ! str_starts_with($asset->file_path, 'media/')
            || ! $disk->exists($asset->file_path)) {
            throw new RuntimeException('Uploaded WebP file missing or invalid: '.$mediaTitle);
        }

        $path = $disk->path($asset->file_path);
        if (! is_file($path) || is_link($path)
            || mime_content_type($path) !== 'image/webp'
            || (! empty($asset->checksum)
                && ! hash_equals((string) $asset->checksum, hash_file('sha256', $path)))) {
            throw new RuntimeException('File content or checksum mismatch for '.$mediaTitle);
        }

        $oldOg = null;
        if ($hasSeo) {
            $oldOg = DB::table('seo_metadata')
                ->where('content_entry_id', $post->id)
                ->value('og_media_id');
        }

        $items[] = [
            'post_id' => (int) $post->id,
            'post_title' => $post->title,
            'media_id' => (int) $asset->id,
            'media_title' => $asset->title,
            'previous_featured_media_id' => $post->featured_media_id,
            'previous_og_media_id' => $oldOg,
            'previous_editorial_status' => $hasReview ? $post->editorial_status : null,
        ];
    }

    if (count($items) !== 6
        || count(array_unique(array_column($items, 'post_id'))) !== 6
        || count(array_unique(array_column($items, 'media_id'))) !== 6) {
        throw new RuntimeException('The six records are not one-to-one; nothing was changed.');
    }

    if ($mode === '--plan') {
        echo json_encode([
            'mode' => 'READ_ONLY_ASSIGNMENT_PLAN',
            'target' => 'production',
            'count' => count($items),
            'seo_social_image_column_available' => $hasSeo,
            'editorial_approval_columns_available' => $hasReview,
            'assignments' => $items,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
        exit(0);
    }

    $changes = DB::transaction(function () use ($items, $hasReview, $hasRevisions, $hasSeo): int {
        $changed = 0;

        // Lock all six content rows before changing any of them.
        $locks = [];
        foreach ($items as $item) {
            $row = DB::table('content_entries')
                ->where('id', $item['post_id'])
                ->lockForUpdate()
                ->first();

            if (! $row || $row->status !== 'draft'
                || $row->title !== $item['post_title']
                || (int) ($row->featured_media_id ?? 0)
                    !== (int) ($item['previous_featured_media_id'] ?? 0)) {
                throw new RuntimeException('Draft changed since planning: '.$item['post_title']);
            }
            if ($hasSeo) {
                $actualOg = DB::table('seo_metadata')
                    ->where('content_entry_id', $row->id)
                    ->value('og_media_id');
                if ((int) ($actualOg ?? 0) !== (int) ($item['previous_og_media_id'] ?? 0)) {
                    throw new RuntimeException('Social metadata changed since planning: '.$item['post_title']);
                }
            }
            $locks[] = $row;
        }

        foreach ($items as $item) {
            $id = $item['post_id'];
            $assetId = $item['media_id'];
            $featuredChanged = (int) ($item['previous_featured_media_id'] ?? 0) !== $assetId;
            $ogChanged = $hasSeo
                && (int) ($item['previous_og_media_id'] ?? 0) !== $assetId;

            if (! $featuredChanged && ! $ogChanged) {
                echo 'ALREADY_ASSIGNED='.$item['media_title']."\n";
                continue;
            }

            if ($hasRevisions) {
                EditorialGate::revision(ContentEntry::query()->findOrFail($id), null, 'before_media_edit');
            }

            $update = ['featured_media_id' => $assetId, 'updated_at' => now()];
            if ($hasReview) {
                $update += [
                    'editorial_status' => 'draft',
                    'editorial_approved_at' => null,
                    'editorial_approved_hash' => null,
                    'scheduled_for' => null,
                ];
            }
            DB::table('content_entries')->where('id', $id)->update($update);

            if ($hasSeo && $ogChanged) {
                $seo = DB::table('seo_metadata')->where('content_entry_id', $id);
                if ($seo->exists()) {
                    $seo->update(['og_media_id' => $assetId, 'updated_at' => now()]);
                } else {
                    DB::table('seo_metadata')->insert([
                        'content_entry_id' => $id,
                        'og_media_id' => $assetId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if ($hasRevisions) {
                EditorialGate::revision(ContentEntry::query()->findOrFail($id), null, 'after_media_edit');
            }

            if (Schema::hasTable('audit_events')) {
                DB::table('audit_events')->insert([
                    'user_id' => null,
                    'action' => 'content.editorial.bulk_image_assignment',
                    'subject_type' => 'ContentEntry',
                    'subject_id' => $id,
                    'ip_hash' => null,
                    'details' => json_encode([
                        'source' => 'administrator_requested_six_draft_images',
                        'previous_featured_media_id' => $item['previous_featured_media_id'],
                        'new_featured_media_id' => $assetId,
                        'previous_og_media_id' => $item['previous_og_media_id'],
                        'new_og_media_id' => $hasSeo ? $assetId : null,
                        'approval_invalidated' => $hasReview,
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            }
            ++$changed;
            echo 'ASSIGNED='.$item['media_title'].' => '.$item['post_title']."\n";
        }
        return $changed;
    }, 3);

    foreach ($items as $item) {
        $row = DB::table('content_entries')->find($item['post_id']);
        if (! $row || $row->status !== 'draft'
            || (int) $row->featured_media_id !== $item['media_id']) {
            throw new RuntimeException('Post-transaction verification failed for '.$item['post_title']);
        }
        if ($hasSeo && (int) DB::table('seo_metadata')
                ->where('content_entry_id', $row->id)->value('og_media_id') !== $item['media_id']) {
            throw new RuntimeException('Social image verification failed for '.$item['post_title']);
        }
    }
    echo 'SIX_ARTICLE_FEATURED_IMAGES=VERIFIED'."\n";
    echo 'SOCIAL_IMAGES='.($hasSeo ? 'VERIFIED' : 'SEO_SCHEMA_NOT_YET_DEPLOYED')."\n";
    echo 'RECORDS_CHANGED='.$changes."\n";
    echo 'ALL_ARTICLES_REMAIN_DRAFT=YES'."\n";
    echo 'PUBLICATION_OR_SCHEDULING=NOT_PERFORMED'."\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ASSIGNMENT_STOPPED='.$e->getMessage()."\n");
    fwrite(STDERR, "No partial transaction writes were committed when an error occurred inside the transaction.\n");
    exit(3);
}
