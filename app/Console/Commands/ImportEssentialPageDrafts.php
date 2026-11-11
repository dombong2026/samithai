<?php

namespace App\Console\Commands;

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
 * Explicit, safe six-page draft import. No routes, publishing, migration,
 * storefront layout or existing content are changed by this command.
 */
class ImportEssentialPageDrafts extends Command
{
    protected $signature = 'samithai:import-essential-pages
        {--check : Validate source, ownership and conflicts without database writes}
        {--apply : Create only missing, unpublished pages after an operator backup}
        {--author-email= : Email of an existing administrator who owns the drafts}';

    protected $description = 'Import six reviewed-ready Samith AI informational page drafts without publishing.';

    private const FILES = [
        '01-platform-and-creators.json' => 3,
        '02-trust-and-guidance.json' => 3,
    ];
    private const PATH = 'database/content/essential-pages-2026-11/';

    public function handle(): int
    {
        if ((bool) $this->option('check') === (bool) $this->option('apply')) {
            $this->error('Specify exactly one of --check or --apply.'); return self::FAILURE;
        }
        $email = trim((string) $this->option('author-email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('An existing administrator --author-email is required.'); return self::FAILURE;
        }

        try {
            $admin = User::query()->whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();
            if (!$admin?->isAdmin()) {
                throw new RuntimeException('Only an existing administrator may own system information pages.');
            }
            $pages = $this->loadAndValidate();
            $this->assertNoConflicts($pages, $admin);
            $existing = ContentEntry::query()->where('kind', 'page')
                ->whereIn('slug', array_column($pages, 'slug'))->count();
            $this->line('CONTENT_BUNDLE=6_COMPLETE_PAGES_VERIFIED');
            $this->line('EXISTING_MATCHING_DRAFTS='.$existing);
            $this->line('NEW_DRAFTS_TO_CREATE='.(6-$existing));
            if ($this->option('check')) {
                $this->info('READ_ONLY_PREFLIGHT=PASSED');
                $this->line('PRODUCTION_DATABASE=UNCHANGED');
                return self::SUCCESS;
            }

            $created = DB::transaction(function () use ($pages, $admin): int {
                $this->assertNoConflicts($pages, $admin);
                $n = 0;
                foreach ($pages as $entry) {
                    if (ContentEntry::query()->where('kind','page')->where('slug',$entry['slug'])->exists()) {
                        continue;
                    }
                    ContentEntry::query()->create([
                        'kind' => 'page',
                        'user_id' => $admin->getKey(),
                        'title' => $entry['title'],
                        'slug' => $entry['slug'],
                        'excerpt' => $entry['excerpt'],
                        'body_html' => SafeHtml::clean($entry['body_html']),
                        'status' => 'draft',
                        'published_at' => null,
                        'featured_media_id' => null,
                    ]);
                    ++$n;
                }
                return $n;
            }, 3);

            $this->info('ESSENTIAL_PAGE_DRAFT_IMPORT=SUCCESS');
            $this->line('NEW_DRAFTS_CREATED='.$created);
            $this->line('PAGES_PUBLISHED=0');
            $this->line('NO_DATABASE_MIGRATION_OR_PUBLIC_LAYOUT_CHANGE=VERIFIED');
            $this->line('NEXT=Review each private preview at Dashboard > Pages, confirm links and legal wording, then publish individually.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('BLOCKED='.$e->getMessage());
            $this->line('DATABASE_IMPORT=FAILED_OR_NOT_CONFIRMED');
            return self::FAILURE;
        }
    }

    private function loadAndValidate(): array
    {
        $all = []; $seen = []; $titles = [];
        foreach (self::FILES as $file => $expected) {
            $path = base_path(self::PATH.$file);
            if (!is_file($path) || is_link($path)) {
                throw new RuntimeException('Missing/unsafe page source: '.$file);
            }
            try {
                $bundle = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new RuntimeException('Invalid page JSON: '.$file);
            }
            if (!is_array($bundle)
                || ($bundle['schema'] ?? '') !== 'samithai.page-drafts.v1'
                || ($bundle['batch_id'] ?? '') !== 'samithai-core-pages-2026-11'
                || !is_array($bundle['pages'] ?? null)
                || count($bundle['pages']) !== $expected
                || !array_is_list($bundle['pages'])) {
                throw new RuntimeException('Unexpected page bundle format: '.$file);
            }
            foreach ($bundle['pages'] as $row) {
                if (!is_array($row)
                    || !self::validString($row['title'] ?? null, 160, 8)
                    || !self::validString($row['slug'] ?? null, 180, 8)
                    || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $row['slug'])
                    || !self::validString($row['excerpt'] ?? null, 3000, 90)
                    || !self::validString($row['body_html'] ?? null, 180000, 3000)
                    || !self::validString($row['seo_title'] ?? null, 160, 20)
                    || !self::validString($row['meta_description'] ?? null, 300, 100)
                    || !is_array($row['review_notes'] ?? null)
                    || count($row['review_notes']) < 2) {
                    throw new RuntimeException('Invalid page record in '.$file);
                }
                foreach ($row['review_notes'] as $note) {
                    if (!self::validString($note, 250, 10)) {
                        throw new RuntimeException('Invalid editorial note in '.$file);
                    }
                }
                $html = $row['body_html'];
                if (preg_match('~<(?:script|iframe|form|input|style|svg)\b|javascript:|data:~i', $html)
                    || preg_match('~href\s*=\s*["\'](?!https://samithai\.com/)~i', $html)
                    || substr_count($html, '<h2>') < 3
                    || strlen(SafeHtml::clean($html)) < 2500) {
                    throw new RuntimeException('Unsafe/incomplete HTML in '.$row['slug']);
                }
                if (isset($seen[$row['slug']]) || isset($titles[Str::lower($row['title'])])) {
                    throw new RuntimeException('Duplicate slug/title: '.$row['slug']);
                }
                $seen[$row['slug']] = true;
                $titles[Str::lower($row['title'])] = true;
                $all[] = $row;
            }
        }

        $required = [
            'about-samith-ai', 'how-it-works', 'become-a-creator',
            'prompt-quality-standards', 'ai-tools-and-compatibility', 'responsible-ai-usage',
        ];
        if (count($all) !== 6 || array_keys($seen) !== $required) {
            throw new RuntimeException('Incorrect page count/order or slugs.');
        }
        return $all;
    }

    private static function validString(mixed $input, int $max, int $min): bool
    {
        return is_string($input) && mb_strlen(trim($input)) >= $min
            && mb_strlen($input) <= $max;
    }

    private function assertNoConflicts(array $pages, User $owner): void
    {
        foreach ($pages as $page) {
            $sameTitle = ContentEntry::query()->where('kind','page')
                ->whereRaw('LOWER(title) = ?', [Str::lower($page['title'])])
                ->where('slug','!=',$page['slug'])->exists();
            if ($sameTitle) {
                throw new RuntimeException('Another page has the same title: '.$page['title']);
            }
            $existing = ContentEntry::query()
                ->where('kind','page')->where('slug',$page['slug'])->first();
            if (!$existing) continue;
            if ((int) $existing->user_id !== (int) $owner->getKey()
                || $existing->title !== $page['title']
                || $existing->excerpt !== $page['excerpt']
                || $existing->body_html !== SafeHtml::clean($page['body_html'])
                || $existing->status !== 'draft'
                || $existing->published_at !== null
                || $existing->featured_media_id !== null) {
                throw new RuntimeException('Existing page is different; no overwrite allowed: '.$page['slug']);
            }
        }
    }
}
