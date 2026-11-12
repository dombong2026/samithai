<?php

namespace App\Console\Commands;

use App\Models\ContentEntry;
use App\Models\SeoMetadata;
use App\Models\User;
use App\Support\SafeHtml;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

class SeedEducationalBlogSeo extends Command
{
    protected $signature = 'samithai:seed-educational-seo
        {--check : Validate eight SEO records without changing anything}
        {--apply : Create only missing SEO records, with no publication}
        {--author-email= : Existing administrator email matching the original blog drafts}';

    protected $description = 'Import reviewed SEO recommendations for the first eight educational articles without overwriting changes.';

    private const PATH = 'database/content/educational-blog-2026-11/';
    private const FILES = [
        '01-fundamentals.json','02-business-and-teaching.json',
        '03-website-and-social.json','04-quality-and-agents.json',
    ];

    public function handle(): int
    {
        if ((bool) $this->option('check') === (bool) $this->option('apply')) {
            $this->error('Choose exactly one of --check or --apply.'); return self::FAILURE;
        }
        $email = trim((string) $this->option('author-email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('An existing administrator email is required.'); return self::FAILURE;
        }
        try {
            $admin = User::query()->whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();
            if (!$admin?->isAdmin()) throw new RuntimeException('EDITORIAL_OWNER_NOT_ADMIN');
            $articles = $this->sources();
            $this->validateExisting($articles, $admin);
            $found = SeoMetadata::query()->whereIn('content_entry_id',
                ContentEntry::query()->where('kind','post')
                    ->whereIn('slug',array_keys($articles))->pluck('id'))->count();
            $this->line('EDUCATIONAL_SEO_BUNDLE=8_VERIFIED');
            $this->line('EXISTING_MATCHING_SEO='.$found);
            $this->line('NEW_SEO_RECORDS_TO_CREATE='.(8-$found));

            if ($this->option('check')) {
                $this->info('READ_ONLY_SEO_PREFLIGHT=PASSED');
                $this->line('DATABASE_AND_PUBLICATION=UNCHANGED');
                return self::SUCCESS;
            }

            $count = DB::transaction(function () use ($articles, $admin): int {
                $this->validateExisting($articles,$admin);
                $created = 0;
                foreach ($articles as $slug=>$article) {
                    $entry = ContentEntry::query()->where('kind','post')->where('slug',$slug)->firstOrFail();
                    if ($entry->seo()->exists()) continue;
                    $entry->seo()->create($this->payload($article));
                    ++$created;
                }
                return $created;
            },3);

            $this->info('EDUCATIONAL_SEO_IMPORT=SUCCESS');
            $this->line('NEW_SEO_RECORDS_CREATED='.$count);
            $this->line('CONTENT_PUBLICATION_CHANGES=0');
            $this->line('PROMPT_PRODUCTS_UNCHANGED=VERIFIED');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('BLOCKED='.$e->getMessage());
            return self::FAILURE;
        }
    }

    private function sources(): array
    {
        $articles = [];
        foreach (self::FILES as $file) {
            $path = base_path(self::PATH.$file);
            if (!is_file($path) || is_link($path)) throw new RuntimeException('MISSING_SEO_SOURCE:'.$file);
            try {
                $payload=json_decode((string)file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new RuntimeException('INVALID_SEO_SOURCE:'.$file);
            }
            if (!is_array($payload) || ($payload['schema'] ?? '') !== 'samithai.blog-drafts.v1'
                || count($payload['articles'] ?? []) !== 2) {
                throw new RuntimeException('INVALID_SEO_BUNDLE:'.$file);
            }
            foreach ($payload['articles'] as $article) {
                $slug=$article['slug'] ?? null;
                if (!is_string($slug) || isset($articles[$slug])
                    || !is_string($article['seo_title'] ?? null)
                    || !is_string($article['meta_description'] ?? null)
                    || mb_strlen($article['seo_title']) > 160
                    || mb_strlen($article['meta_description']) > 300) {
                    throw new RuntimeException('INVALID_SEO_RECORD:'.$file);
                }
                $articles[$slug]=$article;
            }
        }
        if (count($articles)!==8) throw new RuntimeException('SEO_ARTICLE_COUNT_MISMATCH');
        return $articles;
    }

    private function payload(array $a): array
    {
        return [
            'seo_title'=>$a['seo_title'],
            'meta_description'=>$a['meta_description'],
            'og_title'=>$a['seo_title'],
            'og_description'=>$a['meta_description'],
            'robots_index'=>true,
        ];
    }

    private function validateExisting(array $articles, User $admin): void
    {
        foreach ($articles as $slug=>$a) {
            $entry=ContentEntry::query()->with('seo')->where('kind','post')->where('slug',$slug)->first();
            if (!$entry || (int)$entry->user_id !== (int)$admin->id
                || $entry->title !== $a['title']
                || $entry->excerpt !== $a['excerpt']
                || $entry->body_html !== SafeHtml::clean($a['body_html'])) {
                throw new RuntimeException('BLOG_CONTENT_CHANGED_OR_MISSING:'.$slug);
            }
            if ($entry->seo) {
                $expected=$this->payload($a);
                foreach ($expected as $key=>$value) {
                    if ($entry->seo->{$key} !== $value) {
                        throw new RuntimeException('EXISTING_SEO_WOULD_BE_OVERWRITTEN:'.$slug);
                    }
                }
                if ($entry->seo->canonical_path || $entry->seo->og_media_id) {
                    throw new RuntimeException('EXISTING_SEO_CUSTOMIZED:'.$slug);
                }
            }
        }
    }
}
