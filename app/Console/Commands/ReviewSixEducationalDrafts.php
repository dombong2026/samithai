<?php

namespace App\Console\Commands;

use App\Models\ContentEntry;
use App\Support\EditorialGate;
use App\Support\SafeHtml;
use App\Support\SeoHead;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Read-only checklist for the six previously imported educational drafts.
 *
 * This report is a reviewer aid, not a substitute for human verification
 * of factual claims, image rights, or the live destination links.
 */
final class ReviewSixEducationalDrafts extends Command
{
    protected $signature = 'samithai:review-six-educational-drafts
        {--json : Print a structured, non-private JSON report}
        {--strict : Fail when automatic source/media/SEO checks are incomplete}';

    protected $description = 'Read-only readiness report for six unpublished educational articles with their selected images.';

    private const SOURCES = [
        '02-business-and-teaching.json',
        '03-website-and-social.json',
        '04-quality-and-agents.json',
    ];

    private const IMAGE_TITLES = [
        '20-practical-ai-prompts-for-small-businesses' => '01_small-business-ai-prompts',
        'how-teachers-can-use-ai-to-prepare-lesson-plans' => '02_teachers-ai-lesson-plans',
        'how-to-create-a-professional-website-using-ai' => '03_ai-website-from-brief-to-launch',
        '15-ai-prompts-for-social-media-marketing' => '04_social-media-ai-prompts',
        'how-to-test-and-improve-an-ai-prompt' => '05_ai-prompt-quality-checklist',
        'ai-prompt-engineering-vs-ai-agents' => '06_prompt-engineering-vs-ai-agents',
    ];

    public function handle(): int
    {
        try {
            foreach (['content_entries','media_assets','seo_metadata'] as $table) {
                if (! Schema::hasTable($table)) {
                    throw new RuntimeException('MIGRATION_NOT_INSTALLED:'.$table);
                }
            }

            $sources = $this->sources();
            $rows = [];
            $issues = 0;
            foreach (self::IMAGE_TITLES as $slug => $expectedMediaTitle) {
                $source = $sources[$slug] ?? null;
                if (! $source) {
                    throw new RuntimeException('MISSING_SOURCE_ARTICLE:'.$slug);
                }

                $entry = ContentEntry::query()->with(['media','seo.ogMedia','reviewer'])
                    ->where('kind','post')->where('slug',$slug)->first();
                $findings = [];

                if (! $entry) {
                    $findings[] = 'ARTICLE_NOT_IN_DATABASE';
                } elseif ($entry->status !== 'draft') {
                    $findings[] = 'ALREADY_PUBLISHED_OR_UNEXPECTED_STATUS';
                }

                $sourceMatches = $entry !== null
                    && $entry->title === $source['title']
                    && $entry->excerpt === $source['excerpt']
                    && $entry->body_html === SafeHtml::clean($source['body_html']);
                if (! $sourceMatches) {
                    $findings[] = 'CONTENT_REQUIRES_MANUAL_COMPARISON';
                }

                $asset = $entry?->media;
                $mediaMatches = $asset?->kind === 'image'
                    && $asset->title === $expectedMediaTitle
                    && $asset->mime === 'image/webp'
                    && filled($asset->file_path)
                    && Storage::disk('local')->exists($asset->file_path);
                if (! $mediaMatches) $findings[] = 'FEATURED_IMAGE_NOT_VERIFIED';

                $seoSaved = $entry?->seo !== null
                    && filled($entry->seo->seo_title)
                    && filled($entry->seo->meta_description);
                if (! $seoSaved) $findings[] = 'SEO_METADATA_NOT_SAVED';

                $seo = $entry ? SeoHead::content($entry, null, true) : null;
                $usesFeaturedFallback = $entry && ! $entry->seo?->og_media_id && $mediaMatches;
                $effectiveImageVerified = $seo !== null
                    && filled($seo['image'])
                    && ($usesFeaturedFallback || $entry?->seo?->ogMedia?->kind === 'image');
                if (! $effectiveImageVerified) $findings[] = 'SOCIAL_IMAGE_MISSING';

                $body = $entry?->body_html ?? $source['body_html'];
                $text = trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $words = count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
                $h2Count = preg_match_all('/<h2\b/iu',$body);
                $hrefCount = preg_match_all('/\bhref\s*=/iu',$body);
                $approved = $entry ? EditorialGate::approved($entry) : false;

                $rows[] = [
                    'slug' => $slug,
                    'title' => $source['title'],
                    'public_status' => $entry?->status ?? 'missing',
                    'editorial_status' => $entry?->editorial_status ?? 'missing',
                    'matches_imported_source' => $sourceMatches,
                    'word_count' => $words,
                    'section_headings' => $h2Count,
                    'links_to_review' => $hrefCount,
                    'featured_media_id' => $asset?->id,
                    'featured_media_title' => $asset?->title,
                    'featured_media_verified' => $mediaMatches,
                    'social_image_strategy' => $usesFeaturedFallback ? 'assigned_featured_fallback'
                        : ($entry?->seo?->ogMedia?->kind === 'image' ? 'custom_image' : 'missing'),
                    'seo_saved' => $seoSaved,
                    'seo_title_characters' => mb_strlen($entry?->seo?->seo_title ?: $source['seo_title']),
                    'description_characters' => mb_strlen($entry?->seo?->meta_description ?: $source['meta_description']),
                    'indexing_disabled_while_draft' => $seo ? ! $seo['index'] : null,
                    'current_human_approval' => $approved,
                    'source_review_notes' => $source['review_notes'] ?? [],
                    'automatic_findings' => $findings,
                    'requires_real_human_review' => true,
                ];
                $issues += count($findings);
            }

            $report = [
                'status' => $issues > 0 ? 'REVIEW_REQUIRED' : 'AUTOMATIC_CHECKS_PASS',
                'read_only' => true,
                'article_count' => count($rows),
                'automatic_finding_count' => $issues,
                'articles' => $rows,
                'human_review_and_publication' => 'NOT_PERFORMED',
                'database' => 'UNCHANGED',
            ];
            if ($this->option('json')) {
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            } else {
                foreach ($rows as $row) {
                    $this->line($row['title'].' :: '.implode(',', $row['automatic_findings'] ?: ['AUTO_CHECKS_OK']));
                }
                $this->line('AUTOMATIC_FINDINGS='.$issues);
                $this->warn('Human review of claims, links, language and image rights remains required.');
            }
            return $this->option('strict') && $issues > 0 ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('REVIEW_BLOCKED='.$e->getMessage());
            $this->line('DATABASE=UNCHANGED');
            return self::FAILURE;
        }
    }

    private function sources(): array
    {
        $articles = [];
        foreach (self::SOURCES as $filename) {
            $path = base_path('database/content/educational-blog-2026-11/'.$filename);
            if (! is_file($path) || is_link($path)) {
                throw new RuntimeException('MISSING_SOURCE_FILE:'.$filename);
            }
            $group = json_decode((string) file_get_contents($path),true,flags: JSON_THROW_ON_ERROR);
            if (! is_array($group) || ($group['schema'] ?? '') !== 'samithai.blog-drafts.v1') {
                throw new RuntimeException('INVALID_SOURCE_FILE:'.$filename);
            }
            foreach ($group['articles'] ?? [] as $article) {
                $slug = $article['slug'] ?? null;
                if (! is_string($slug) || isset($articles[$slug])) {
                    throw new RuntimeException('DUPLICATED_OR_INVALID_ARTICLE:'.$filename);
                }
                $articles[$slug] = $article;
            }
        }
        if (count($articles) !== 6) {
            throw new RuntimeException('EXPECTED_SIX_SOURCE_ARTICLES');
        }
        return $articles;
    }
}
