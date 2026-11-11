<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\User;
use App\Support\SafeHtml;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Editorial source is JSON, while the current post CMS accepts title, slug,
 * excerpt and safely sanitized HTML. SEO suggestions/outline remain in source.
 *
 * This is NEVER called by an installer, scheduler or database seeder.
 * Operators must explicitly run --check, then --apply after a MySQL backup.
 */
class ImportEducationalBlogDrafts extends Command
{
    protected $signature = 'samithai:import-educational-blogs
        {--check : Verify eight blog articles and potential conflicts without writing}
        {--apply : Insert only missing, unpublished blog records}
        {--author-email= : Existing Samith AI administrator email that owns these drafts}';

    protected $description = 'Import eight fully written educational blog articles as editable, unpublished drafts.';

    private const PATH = 'database/content/educational-blog-2026-11/';
    private const FILES = [
        '01-fundamentals.json' => 'fundamentals',
        '02-business-and-teaching.json' => 'business-and-teaching',
        '03-website-and-social.json' => 'website-and-social',
        '04-quality-and-agents.json' => 'quality-and-agents',
    ];

    private const SLUGS = [
        'what-is-an-ai-prompt-beginners-guide',
        'how-to-write-better-prompts-for-ai-assistants',
        '20-practical-ai-prompts-for-small-businesses',
        'how-teachers-can-use-ai-to-prepare-lesson-plans',
        'how-to-create-a-professional-website-using-ai',
        '15-ai-prompts-for-social-media-marketing',
        'how-to-test-and-improve-an-ai-prompt',
        'ai-prompt-engineering-vs-ai-agents',
    ];

    public function handle(): int
    {
        if ((bool) $this->option('check') === (bool) $this->option('apply')) {
            $this->error('Choose exactly one of --check or --apply.'); return self::FAILURE;
        }

        $email = trim((string) $this->option('author-email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Provide the email of an existing administrator.'); return self::FAILURE;
        }

        try {
            $admin = User::query()->whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();
            if (!$admin?->isAdmin()) {
                throw new RuntimeException('Only an existing administrator may own these platform editorial drafts.');
            }
            $articles = $this->loadAndValidate();
            $this->assertCategoryLinksValid($articles);
            $this->assertNoConflicts($articles, $admin);

            $existing = ContentEntry::query()->where('kind', 'post')
                ->whereIn('slug', array_column($articles, 'slug'))->count();
            $this->line('EDUCATIONAL_ARTICLES=8_FULL_DRAFTS_VERIFIED');
            $this->line('CATEGORIES_LINKED=VERIFIED');
            $this->line('EXISTING_MATCHING_BLOG_DRAFTS='.$existing);
            $this->line('NEW_BLOG_DRAFTS_TO_CREATE='.(8 - $existing));

            if ($this->option('check')) {
                $this->info('READ_ONLY_BLOG_PREFLIGHT=PASSED');
                $this->line('DATABASE_AND_PUBLIC_BLOG=UNCHANGED');
                return self::SUCCESS;
            }

            $created = DB::transaction(function () use ($articles, $admin): int {
                $this->assertCategoryLinksValid($articles);
                $this->assertNoConflicts($articles, $admin);
                $count = 0;
                foreach ($articles as $article) {
                    if (ContentEntry::query()
                        ->where('kind', 'post')->where('slug', $article['slug'])->exists()) {
                        continue;
                    }

                    ContentEntry::query()->create([
                        'kind' => 'post',
                        'user_id' => $admin->getKey(),
                        'title' => $article['title'],
                        'slug' => $article['slug'],
                        'excerpt' => $article['excerpt'],
                        'body_html' => SafeHtml::clean($article['body_html']),
                        'featured_media_id' => null,
                        'status' => 'draft',
                        'published_at' => null,
                    ]);
                    ++$count;
                }
                return $count;
            }, 3);

            $this->info('EDUCATIONAL_BLOG_DRAFT_IMPORT=SUCCESS');
            $this->line('NEW_BLOG_DRAFTS_CREATED='.$created);
            $this->line('BLOG_ARTICLES_PUBLISHED=0');
            $this->line('DATABASE_MIGRATIONS=NONE');
            $this->line('STOREFRONT_AND_PAYMENT_CODE=UNCHANGED');
            $this->line('NEXT=Review all eight in Dashboard > Post Manager, verify links, SEO suggestions, and publish individually after approval.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('BLOCKED='.$exception->getMessage());
            $this->line('BLOG_DRAFT_IMPORT=NOT_CONFIRMED');
            return self::FAILURE;
        }
    }

    private function loadAndValidate(): array
    {
        $articles = []; $uniqueSlugs = []; $uniqueTitles = [];
        foreach (self::FILES as $filename => $group) {
            $file = base_path(self::PATH.$filename);
            if (!is_file($file) || is_link($file)) {
                throw new RuntimeException('Missing/unsafe editorial file: '.$filename);
            }
            try {
                $bundle = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new RuntimeException('Invalid JSON in '.$filename);
            }
            if (!is_array($bundle)
                || ($bundle['schema'] ?? null) !== 'samithai.blog-drafts.v1'
                || ($bundle['batch_id'] ?? null) !== 'samithai-education-blog-2026-11'
                || ($bundle['group'] ?? null) !== $group
                || !is_array($bundle['articles'] ?? null)
                || count($bundle['articles']) !== 2
                || !array_is_list($bundle['articles'])) {
                throw new RuntimeException('Invalid article bundle '.$filename);
            }
            foreach ($bundle['articles'] as $article) {
                if (!is_array($article)
                    || !self::validText($article['title'] ?? null, 160, 15)
                    || !self::validText($article['slug'] ?? null, 180, 15)
                    || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $article['slug'])
                    || !self::validText($article['excerpt'] ?? null, 3000, 110)
                    || !self::validText($article['seo_title'] ?? null, 160, 30)
                    || !self::validText($article['meta_description'] ?? null, 300, 100)
                    || !self::validText($article['primary_keyword'] ?? null, 120, 8)
                    || !self::validText($article['audience'] ?? null, 300, 20)
                    || !self::validText($article['body_html'] ?? null, 180000, 6000)
                    || ($article['status'] ?? null) !== 'draft'
                    || !is_array($article['outline'] ?? null)
                    || count($article['outline']) < 5
                    || !array_is_list($article['outline'])
                    || !is_array($article['review_notes'] ?? null)
                    || count($article['review_notes']) < 3
                    || !is_array($article['category_links'] ?? null)
                    || count($article['category_links']) < 2
                    || !array_is_list($article['category_links'])
                    || ($article['word_count_estimate'] ?? 0) < 900) {
                    throw new RuntimeException('Incomplete or invalid blog article in '.$filename);
                }
                $html = $article['body_html'];
                if (preg_match('~<(?:script|iframe|form|input|style|svg|object)\b|javascript:|data:~i', $html)
                    || preg_match('~href\s*=\s*["\'](?!https://samithai\.com/)~i', $html)
                    || strlen(SafeHtml::clean($html)) < 5000
                    || str_contains($html, '[INSERT ARTICLE]')) {
                    throw new RuntimeException('Unsafe or placeholder HTML: '.$article['slug']);
                }

                preg_match_all('~<h2>(.*?)</h2>~su', $html, $headers);
                if (($headers[1] ?? []) !== $article['outline']) {
                    throw new RuntimeException('SEO outline does not match H2 structure: '.$article['slug']);
                }
                foreach ($article['review_notes'] as $note) {
                    if (!self::validText($note, 250, 12)) {
                        throw new RuntimeException('Invalid review note '.$article['slug']);
                    }
                }
                foreach ($article['category_links'] as $link) {
                    if (!is_array($link)
                        || !self::validText($link['label'] ?? null, 100, 4)
                        || !self::validText($link['slug'] ?? null, 120, 5)
                        || !preg_match('/^[a-z0-9-]+$/D', $link['slug'])
                        || !str_contains($html, 'https://samithai.com/categories/'.$link['slug'])) {
                        throw new RuntimeException('Missing category link: '.$article['slug']);
                    }
                }

                $slug = $article['slug']; $title = Str::lower($article['title']);
                if (isset($uniqueSlugs[$slug]) || isset($uniqueTitles[$title])) {
                    throw new RuntimeException('Duplicate title or slug: '.$slug);
                }
                $uniqueSlugs[$slug] = true; $uniqueTitles[$title] = true;
                $articles[] = $article;
            }
        }

        if (count($articles) !== 8 || array_keys($uniqueSlugs) !== self::SLUGS) {
            throw new RuntimeException('Unexpected article count, ordering, or canonical slugs.');
        }
        return $articles;
    }

    private static function validText(mixed $text, int $max, int $min): bool
    {
        return is_string($text) && mb_strlen(trim($text)) >= $min && mb_strlen($text) <= $max;
    }

    private function assertCategoryLinksValid(array $articles): void
    {
        $slugs = [];
        foreach ($articles as $article) {
            foreach ($article['category_links'] as $link) {
                $slugs[$link['slug']] = true;
            }
        }
        $existing = Category::query()->whereIn('slug', array_keys($slugs))
            ->where('is_active', true)->pluck('slug')->all();

        if (count($existing) !== count($slugs)) {
            throw new RuntimeException('Missing or inactive product category linked in an article.');
        }
    }

    private function assertNoConflicts(array $articles, User $owner): void
    {
        foreach ($articles as $article) {
            if (ContentEntry::query()->where('kind','post')
                ->whereRaw('LOWER(title) = ?', [Str::lower($article['title'])])
                ->where('slug','!=',$article['slug'])->exists()) {
                throw new RuntimeException('Another blog post has the same title: '.$article['title']);
            }
            $current = ContentEntry::query()->where('kind','post')
                ->where('slug',$article['slug'])->first();
            if (!$current) continue;
            if ((int) $current->user_id !== (int) $owner->getKey()
                || $current->title !== $article['title']
                || $current->excerpt !== $article['excerpt']
                || $current->body_html !== SafeHtml::clean($article['body_html'])
                || $current->status !== 'draft'
                || $current->published_at !== null
                || $current->featured_media_id !== null) {
                throw new RuntimeException('Existing post differs or was reviewed; no overwrite allowed: '.$article['slug']);
            }
        }
    }
}
