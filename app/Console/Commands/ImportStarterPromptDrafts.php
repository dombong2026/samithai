<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * An explicit, no-publish import for the curated Samith AI Starter 30 collection.
 *
 * Command is never called by the installer or DatabaseSeeder.
 * --check performs no mutations. --apply creates only draft, $0 listings.
 * No existing prompts, drafts, categories or published content are overwritten.
 */
class ImportStarterPromptDrafts extends Command
{
    protected $signature = 'samithai:import-starter-drafts
        {--check : Validate the content and production conflicts without writing}
        {--apply : Explicitly create missing draft-only records}
        {--author-email= : Existing administrator or creator email to own drafts}';

    protected $description = 'Safely import 30 curated unpublished, free AI prompt products for editorial review.';

    private const CONTENT_DIRECTORY = 'database/content/starter-2026-11';

    private const FILES = [
        '01-marketing-social-media.json' => 6,
        '02-education-teaching.json' => 5,
        '03-business-productivity.json' => 5,
        '04-coding-development.json' => 5,
        '05-ai-image-video.json' => 4,
        '06-ecommerce-sales.json' => 3,
        '07-data-research.json' => 2,
    ];

    public function handle(): int
    {
        if ((bool) $this->option('check') === (bool) $this->option('apply')) {
            $this->error('Choose exactly one of --check or --apply. Nothing changed.');

            return self::FAILURE;
        }

        $email = trim((string) $this->option('author-email'));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('An existing administrator/creator --author-email is required. Nothing changed.');

            return self::FAILURE;
        }

        try {
            $user = User::query()->whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();
            if (!$user || (!$user->isAdmin() && !$user->isCreator())) {
                throw new RuntimeException('Author must be an existing administrator or creator.');
            }

            $packs = $this->loadAndValidate();
            $this->assertNoConflicts($packs, $user);

            $existing = Prompt::query()
                ->whereIn('slug', collect($packs)->flatMap(fn ($x) => array_column($x['products'], 'slug'))->all())
                ->count();
            $missing = 30 - $existing;

            $this->line('CONTENT_BUNDLE=30_PRODUCTS_7_CATEGORIES_VALID');
            $this->line('DRAFTS_ALREADY_MATCHING='.$existing);
            $this->line('DRAFTS_TO_CREATE='.$missing);
            $this->line('DRAFT_OWNER_ROLE='.$user->role);

            if ($this->option('check')) {
                $this->info('READ_ONLY_PREFLIGHT=PASSED');
                $this->line('DATABASE_AND_PUBLIC_STOREFRONT=UNCHANGED');

                return self::SUCCESS;
            }

            // Preflight validation above is deliberately repeated within a
            // transaction to reduce stale-check races. Unique slugs remain
            // protected by database constraints if concurrent imports occur.
            $created = DB::transaction(function () use ($packs, $user): int {
                $this->assertNoConflicts($packs, $user);
                $count = 0;
                foreach ($packs as $pack) {
                    $category = $this->findCategory($pack['category']);
                    if (!$category) {
                        $category = Category::query()->create([
                            'slug' => $pack['category']['slug'],
                            'name' => $pack['category']['name'],
                            'description' => $pack['category']['description'],
                            'is_active' => true,
                        ]);
                    }
                    foreach ($pack['products'] as $entry) {
                        if (Prompt::query()->where('slug', $entry['slug'])->exists()) {
                            continue; // Existing exact draft was checked; never edit it.
                        }

                        Prompt::query()->create([
                            'user_id' => $user->getKey(),
                            'category_id' => $category->getKey(),
                            'title' => $entry['title'],
                            'slug' => $entry['slug'],
                            'summary' => $entry['summary'],
                            'content' => $entry['content'],
                            'price' => '0.00',
                            'status' => 'draft',
                            'license_type' => 'regular',
                            'featured_media_id' => null,
                            'published_at' => null,
                        ]);
                        ++$count;
                    }
                }

                return $count;
            }, 3);

            $this->info('STARTER_DRAFT_IMPORT=SUCCESS');
            $this->line('NEW_DRAFTS_CREATED='.$created);
            $this->line('PUBLIC_PROMPTS_PUBLISHED=0');
            $this->line('PRICE_USD=0.00');
            $this->line('APPROVAL_AND_SUBMISSION=NOT_TRIGGERED');
            $this->line('NEXT=Review drafts in Dashboard > Prompt Manager. Publish only through existing human moderation.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('BLOCKED='.$exception->getMessage());
            $this->line('NEW_DRAFT_IMPORT=NOT_CONFIRMED');
            $this->line('Do not retry with modified data until the conflict has been reviewed.');

            return self::FAILURE;
        }
    }

    private function loadAndValidate(): array
    {
        $packs = [];
        $seenSlugs = [];
        $seenCategorySlugs = [];
        $seenTitles = [];
        $total = 0;

        foreach (self::FILES as $filename => $expectedCount) {
            $path = base_path(self::CONTENT_DIRECTORY.'/'.$filename);
            if (!is_file($path) || is_link($path)) {
                throw new RuntimeException('Missing/unsafe content file: '.$filename);
            }
            $raw = file_get_contents($path);
            if (!is_string($raw) || $raw === '') {
                throw new RuntimeException('Cannot read content file: '.$filename);
            }
            try {
                $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new RuntimeException('Invalid JSON: '.$filename);
            }

            if (!is_array($data)
                || ($data['schema'] ?? null) !== 'samithai.prompt-starter.v1'
                || ($data['batch_id'] ?? null) !== 'samithai-starter-2026-11') {
                throw new RuntimeException('Unexpected manifest schema: '.$filename);
            }
            $category = $data['category'] ?? null;
            if (!is_array($category) || !self::isText($category['name'] ?? null, 100)
                || !self::isText($category['description'] ?? null, 2000)
                || !self::isSlug($category['slug'] ?? null, 120)) {
                throw new RuntimeException('Invalid category: '.$filename);
            }
            if (isset($seenCategorySlugs[$category['slug']])) {
                throw new RuntimeException('Duplicated category slug: '.$category['slug']);
            }
            $seenCategorySlugs[$category['slug']] = true;

            $entries = $data['products'] ?? null;
            if (!is_array($entries) || count($entries) !== $expectedCount || !array_is_list($entries)) {
                throw new RuntimeException('Invalid product count: '.$filename);
            }

            foreach ($entries as $index => $row) {
                if (!is_array($row)
                    || !self::isText($row['title'] ?? null, 160)
                    || !self::isSlug($row['slug'] ?? null, 190)
                    || !str_starts_with($row['slug'], 'starter-2026-')
                    || !self::isText($row['summary'] ?? null, 3000)
                    || !self::isText($row['content'] ?? null, 100000, 700)
                    || ($row['price'] ?? null) !== '0.00'
                    || ($row['status'] ?? null) !== 'draft'
                    || ($row['license_type'] ?? null) !== 'regular'
                    || !self::isText($row['review_scenario'] ?? null, 1500)
                    || !is_array($row['review_checks'] ?? null)
                    || count($row['review_checks']) < 3
                    || count($row['review_checks']) > 10
                    || !array_is_list($row['review_checks'])) {
                    throw new RuntimeException('Invalid draft record: '.$filename.' #'.($index + 1));
                }
                foreach ($row['review_checks'] as $check) {
                    if (!self::isText($check, 300)) {
                        throw new RuntimeException('Invalid review checklist item: '.$filename);
                    }
                }
                foreach (['ROLE & MISSION', 'USER INPUTS', 'METHOD', 'REQUIRED OUTPUT', 'QUALITY & ETHICS RULES'] as $heading) {
                    if (!str_contains($row['content'], $heading)) {
                        throw new RuntimeException('Missing prompt section '.$heading.': '.$row['slug']);
                    }
                }
                $slug = $row['slug'];
                $title = Str::lower($row['title']);
                if (isset($seenSlugs[$slug]) || isset($seenTitles[$title])) {
                    throw new RuntimeException('Duplicate starter title/slug in dataset: '.$slug);
                }
                $seenSlugs[$slug] = true;
                $seenTitles[$title] = true;
                ++$total;
            }
            $packs[] = $data;
        }

        if ($total !== 30 || count($packs) !== 7) {
            throw new RuntimeException('Bundle must contain exactly 30 prompts in seven categories.');
        }

        return $packs;
    }

    private static function isText(mixed $value, int $max, int $min = 1): bool
    {
        return is_string($value) && mb_strlen(trim($value)) >= $min && mb_strlen($value) <= $max;
    }

    private static function isSlug(mixed $value, int $max): bool
    {
        return is_string($value) && strlen($value) <= $max
            && (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value);
    }

    private function findCategory(array $details): ?Category
    {
        $category = Category::query()->where('slug', $details['slug'])->first();
        if ($category && $category->name !== $details['name']) {
            throw new RuntimeException('Category slug belongs to a different category: '.$details['slug']);
        }
        $category ??= Category::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($details['name'])])->first();

        if ($category && !$category->is_active) {
            throw new RuntimeException('Category is inactive; manual review required: '.$details['name']);
        }

        return $category;
    }

    private function assertNoConflicts(array $packs, User $user): void
    {
        foreach ($packs as $pack) {
            $category = $this->findCategory($pack['category']);
            foreach ($pack['products'] as $entry) {
                $existing = Prompt::query()->where('slug', $entry['slug'])->first();
                if (!$existing) continue;

                $same = $existing->user_id === $user->getKey()
                    && $existing->title === $entry['title']
                    && $existing->summary === $entry['summary']
                    && $existing->content === $entry['content']
                    && $existing->status === 'draft'
                    && $existing->published_at === null
                    && (float) $existing->price === 0.0
                    && $existing->license_type === 'regular'
                    && ($category === null || $existing->category_id === $category->getKey());
                if (!$same) {
                    throw new RuntimeException('Existing slug differs; no overwrite allowed: '.$entry['slug']);
                }
            }
        }
    }
}
