<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\User;
use App\Support\FrontendPageAvailability;
use App\Support\SafeHtml;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

class PublishReviewedCoreContent extends Command
{
    protected $signature = 'samithai:publish-reviewed-content
        {--check : Read-only publication readiness audit}
        {--publish-pages : Publish the six approved informational pages atomically}
        {--publish-first-article : Publish only the beginner AI-prompt article}
        {--publish-second-article : Publish only the better-prompting article}
        {--reviewer-email= : Existing administrator who confirms editorial review}
        {--confirm= : Explicit editorial sign-off for the selected publication stage}';

    protected $description = 'Release six reviewed pages, then the first two articles, without touching the remaining blog drafts.';

    private const PAGE_FILES = [
        'database/content/essential-pages-2026-11/01-platform-and-creators.json',
        'database/content/essential-pages-2026-11/02-trust-and-guidance.json',
    ];
    private const BLOG_FILE = 'database/content/educational-blog-2026-11/01-fundamentals.json';
    private const PAGE_SLUGS = [
        'about-samith-ai',
        'how-it-works',
        'become-a-creator',
        'prompt-quality-standards',
        'ai-tools-and-compatibility',
        'responsible-ai-usage',
    ];
    private const ARTICLE_SLUGS = [
        'what-is-an-ai-prompt-beginners-guide',
        'how-to-write-better-prompts-for-ai-assistants',
    ];
    private const CONFIRMATIONS = [
        'publish-pages' => 'PUBLISH-SIX-REVIEWED-PAGES',
        'publish-first-article' => 'PUBLISH-FIRST-REVIEWED-ARTICLE',
        'publish-second-article' => 'PUBLISH-SECOND-REVIEWED-ARTICLE',
    ];

    public function handle(): int
    {
        $actions = [
            'check', 'publish-pages', 'publish-first-article', 'publish-second-article',
        ];
        $chosen = array_values(array_filter(
            $actions, fn (string $action) => (bool) $this->option($action)
        ));
        if (count($chosen) !== 1) {
            $this->error('Choose exactly one action; use --check for a read-only audit.');
            return self::FAILURE;
        }
        $mode = $chosen[0];
        $email = trim((string) $this->option('reviewer-email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Provide --reviewer-email for an existing administrator.');
            return self::FAILURE;
        }
        if ($mode !== 'check' && $this->option('confirm') !== self::CONFIRMATIONS[$mode]) {
            $this->error('BLOCKED=EDITORIAL_APPROVAL_NOT_ATTESTED');
            return self::FAILURE;
        }
        if ($mode === 'check' && filled($this->option('confirm'))) {
            $this->error('Read-only --check does not accept approval confirmation.');
            return self::FAILURE;
        }

        try {
            $reviewer = User::query()->whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();
            if (!$reviewer?->isAdmin()) {
                throw new RuntimeException('REVIEWER_NOT_EXISTING_ADMIN');
            }
            $sources = $this->loadSources();
            $pages = $sources['pages'];
            $posts = $sources['posts'];

            if ($mode === 'check') {
                $records = $this->verifyContent($pages, $posts, $reviewer);
                $this->showReadiness($records);
                $this->info('EDITORIAL_RELEASE_READ_ONLY_PREFLIGHT=PASSED');
                $this->line('DATABASE_AND_PUBLIC_CONTENT=UNCHANGED');
                return self::SUCCESS;
            }

            $published = DB::transaction(function () use ($mode, $reviewer, $pages, $posts): int {
                $records = $this->verifyContent($pages, $posts, $reviewer, true);
                $this->verifyStageDependencies($mode, $records, true);
                $selected = match ($mode) {
                    'publish-pages' => self::PAGE_SLUGS,
                    'publish-first-article' => [self::ARTICLE_SLUGS[0]],
                    'publish-second-article' => [self::ARTICLE_SLUGS[1]],
                };

                $count = 0;
                foreach ($selected as $slug) {
                    $entry = $records[$slug];
                    if ($entry->status === 'published') {
                        continue;
                    }
                    $entry->update([
                        'status' => 'published',
                        'published_at' => now(),
                    ]);
                    AuditEvent::query()->create([
                        'user_id' => $reviewer->getKey(),
                        'action' => 'content.status',
                        'subject_type' => class_basename($entry),
                        'subject_id' => $entry->getKey(),
                        'ip_hash' => null,
                        'details' => [
                            'status' => 'published',
                            'editorial_release' => 'samithai-2026-core-content',
                            'editorial_stage' => $mode,
                            'attested_review' => true,
                        ],
                        'created_at' => now(),
                    ]);
                    ++$count;
                }
                return $count;
            }, 3);

            $this->info('EDITORIAL_PUBLICATION_STAGE=SUCCESS');
            $this->line('STAGE='.$mode);
            $this->line('NEWLY_PUBLISHED_RECORDS='.$published);
            $this->line('PUBLICATION_AUDIT_EVENTS_CREATED='.$published);
            $this->line('OTHER_SIX_EDUCATIONAL_ARTICLES=UNCHANGED_DRAFTS');
            $this->line('DATABASE_MIGRATIONS=NONE');
            $this->line('FRONTEND_PAYMENT_AND_ACCOUNT_CODE=UNCHANGED');
            $this->line('NEXT=Run --check and verify each public URL in the browser.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('BLOCKED='.$e->getMessage());
            $this->line('PUBLICATION_NOT_CONFIRMED');
            return self::FAILURE;
        }
    }

    private function readBundle(string $path, string $schema, string $field): array
    {
        $file = base_path($path);
        if (!is_file($file) || is_link($file)) {
            throw new RuntimeException('SOURCE_FILE_MISSING_OR_UNSAFE:'.$path);
        }
        try {
            $payload = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('INVALID_SOURCE_JSON:'.$path);
        }
        if (!is_array($payload)
            || ($payload['schema'] ?? '') !== $schema
            || !is_array($payload[$field] ?? null)) {
            throw new RuntimeException('SOURCE_SCHEMA_MISMATCH:'.$path);
        }
        return $payload[$field];
    }

    private function loadSources(): array
    {
        $pages = [];
        foreach (self::PAGE_FILES as $file) {
            foreach ($this->readBundle($file, 'samithai.page-drafts.v1', 'pages') as $row) {
                if (!isset($row['slug']) || isset($pages[$row['slug']])) {
                    throw new RuntimeException('DUPLICATE_OR_MISSING_PAGE_SLUG');
                }
                $pages[$row['slug']] = $row;
            }
        }
        if (array_keys($pages) !== self::PAGE_SLUGS) {
            throw new RuntimeException('SIX_PAGE_SOURCE_MANIFEST_MISMATCH');
        }

        $posts = [];
        foreach ($this->readBundle(self::BLOG_FILE, 'samithai.blog-drafts.v1', 'articles') as $row) {
            if (!isset($row['slug']) || isset($posts[$row['slug']])) {
                throw new RuntimeException('DUPLICATE_OR_MISSING_BLOG_SLUG');
            }
            $posts[$row['slug']] = $row;
        }
        if (array_keys($posts) !== self::ARTICLE_SLUGS) {
            throw new RuntimeException('TWO_ARTICLE_SOURCE_MANIFEST_MISMATCH');
        }

        foreach (array_merge($pages, $posts) as $row) {
            if (empty($row['title']) || empty($row['excerpt']) || empty($row['body_html'])
                || strlen($row['body_html']) < 3000) {
                throw new RuntimeException('EDITORIAL_SOURCE_INCOMPLETE');
            }
            if (!preg_match_all('~href="https://samithai\.com/([^"]*)"~', $row['body_html'], $matches)) {
                throw new RuntimeException('MISSING_INTERNAL_LINKS:'.$row['slug']);
            }
            foreach ($matches[1] as $target) {
                if (str_starts_with($target, 'pages/')) {
                    $linked = substr($target, 6);
                    if (!array_key_exists($linked, $pages)) {
                        throw new RuntimeException('UNEXPECTED_LINKED_PAGE:'.$linked);
                    }
                }
                if (preg_match('~^categories/([^/?#]+)~', $target, $match)
                    && !Category::query()->where('slug', $match[1])->where('is_active', true)->exists()) {
                    throw new RuntimeException('MISSING_OR_DISABLED_CATEGORY:'.$match[1]);
                }
            }
            if (preg_match('~<(script|iframe|form|input|style)\b|javascript:~i', $row['body_html'])) {
                throw new RuntimeException('UNSAFE_EDITORIAL_SOURCE:'.$row['slug']);
            }
        }

        return compact('pages','posts');
    }

    /**
     * Strict source match ensures that the operator can attest to one exact
     * revision and that a later manual edit does not publish behind their back.
     * Human-edited drafts can still be published through the existing admin UI.
     */
    private function verifyContent(array $pages, array $posts, User $reviewer, bool $lock = false): array
    {
        $records = [];
        foreach (['page' => $pages, 'post' => $posts] as $kind => $items) {
            foreach ($items as $slug => $source) {
                $query = ContentEntry::query()->where('kind', $kind)->where('slug', $slug);
                if ($lock) $query->lockForUpdate();
                $entry = $query->first();
                if (!$entry) {
                    throw new RuntimeException('MISSING_CMS_DRAFT:'.$kind.':'.$slug);
                }
                if ((int) $entry->user_id !== (int) $reviewer->getKey()
                    || $entry->title !== $source['title']
                    || $entry->excerpt !== $source['excerpt']
                    || $entry->body_html !== SafeHtml::clean($source['body_html'])) {
                    throw new RuntimeException('CMS_SOURCE_DRIFT_OR_OWNER_MISMATCH:'.$slug);
                }
                if (!in_array($entry->status, ['draft','published'], true)
                    || ($entry->status === 'draft' && $entry->published_at !== null)
                    || ($entry->status === 'published' && $entry->published_at === null)) {
                    throw new RuntimeException('UNEXPECTED_PUBLICATION_STATE:'.$slug);
                }
                $records[$slug] = $entry;
            }
        }

        $remaining = [
            '20-practical-ai-prompts-for-small-businesses',
            'how-teachers-can-use-ai-to-prepare-lesson-plans',
            'how-to-create-a-professional-website-using-ai',
            '15-ai-prompts-for-social-media-marketing',
            'how-to-test-and-improve-an-ai-prompt',
            'ai-prompt-engineering-vs-ai-agents',
        ];
        foreach ($remaining as $slug) {
            $query = ContentEntry::query()->where('kind', 'post')->where('slug', $slug);
            if ($lock) $query->lockForUpdate();
            $entry = $query->first();
            if (!$entry || $entry->status !== 'draft' || $entry->published_at !== null) {
                throw new RuntimeException('REMAINING_SIX_BLOG_DRAFTS_MUST_STAY_UNPUBLISHED:'.$slug);
            }
        }
        return $records;
    }

    private function verifyStageDependencies(string $mode, array $records, bool $action = false): void
    {
        foreach (['catalog', 'category', 'register', 'support'] as $key) {
            if (!FrontendPageAvailability::enabled($key)) {
                throw new RuntimeException('LINKED_FRONTEND_FEATURE_DISABLED:'.$key);
            }
        }
        if ($mode !== 'publish-pages') {
            foreach (self::PAGE_SLUGS as $slug) {
                if ($records[$slug]->status !== 'published') {
                    throw new RuntimeException('PUBLISH_INFORMATIONAL_PAGES_BEFORE_BLOG:'.$slug);
                }
            }
            foreach (['blog', 'blog_detail'] as $key) {
                if (!FrontendPageAvailability::enabled($key)) {
                    throw new RuntimeException('BLOG_FRONTEND_DISABLED:'.$key);
                }
            }
            foreach (self::ARTICLE_SLUGS as $slug) {
                $article = $records[$slug];
                if ($slug === self::ARTICLE_SLUGS[0] && $mode === 'publish-second-article'
                    && $article->status !== 'published') {
                    throw new RuntimeException('PUBLISH_FIRST_ARTICLE_BEFORE_SECOND');
                }
            }
        }
    }

    private function showReadiness(array $records): void
    {
        $this->line('SOURCE_AND_CMS_CONTENT=EXACT_MATCH');
        $this->line('EDITORIAL_PAGES_DRAFT='.count(array_filter(self::PAGE_SLUGS,
            fn (string $slug) => $records[$slug]->status === 'draft')));
        $this->line('EDITORIAL_PAGES_PUBLISHED='.count(array_filter(self::PAGE_SLUGS,
            fn (string $slug) => $records[$slug]->status === 'published')));
        $this->line('INTRO_BLOG_DRAFT='.count(array_filter(self::ARTICLE_SLUGS,
            fn (string $slug) => $records[$slug]->status === 'draft')));
        $this->line('INTRO_BLOG_PUBLISHED='.count(array_filter(self::ARTICLE_SLUGS,
            fn (string $slug) => $records[$slug]->status === 'published')));
        $this->line('REMAINING_SIX_BLOG_DRAFTS=VERIFIED');
        foreach (['catalog','category','register','support','blog','blog_detail'] as $key) {
            $this->line('PUBLIC_FEATURE_'.strtoupper($key).'='.
                (FrontendPageAvailability::enabled($key) ? 'ENABLED' : 'DISABLED'));
        }
        $this->line('EDITORIAL_REVIEW=REQUIRES_HUMAN_ATTESTATION');
    }
}
