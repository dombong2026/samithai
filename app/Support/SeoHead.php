<?php

namespace App\Support;

use App\Models\ContentEntry;
use App\Models\Prompt;
use App\Models\SiteSetting;
use Illuminate\Support\Str;

final class SeoHead
{
    public static function content(ContentEntry $entry, ?array $translation = null, bool $preview = false): array
    {
        $entry->loadMissing(['seo.ogMedia','media','author']);
        $seo = $entry->seo;
        $title = $translation['title'] ?? $entry->title;
        $excerpt = $translation['excerpt'] ?? $entry->excerpt;
        $type = $entry->kind === 'post' ? 'article' : 'website';
        $article = $entry->kind === 'post';
        $image = self::image($seo?->ogMedia, $entry->media);
        $url = self::canonical(
            $seo?->canonical_path, '/'.($entry->kind === 'post' ? 'blog' : 'pages').'/'.$entry->slug
        );
        $data = $article ? [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $title,
            'description' => self::description($seo?->meta_description, $excerpt),
            'url' => $url,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'author' => ['@type' => 'Person', 'name' => $entry->author?->name ?? 'Editorial team'],
            'publisher' => ['@type' => 'Organization', 'name' => SiteSetting::text('site_name','Samith AI')],
        ] : [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $title,
            'description' => self::description($seo?->meta_description, $excerpt),
            'url' => $url,
        ];
        if ($article) {
            if ($entry->published_at) $data['datePublished'] = $entry->published_at->toIso8601String();
            $data['dateModified'] = $entry->updated_at->toIso8601String();
        }
        if ($image) $data['image'] = $image;

        return [
            'title' => self::title($seo?->seo_title, $title),
            'description' => self::description($seo?->meta_description, $excerpt),
            'canonical' => $url,
            'og_title' => self::title($seo?->og_title, $title),
            'og_description' => self::description($seo?->og_description, $seo?->meta_description ?: $excerpt),
            'og_type' => $type,
            'image' => $image,
            'index' => !$preview && $entry->status === 'published' && ($seo?->robots_index ?? true),
            'schema' => $preview || $entry->status !== 'published' ||
                ($seo?->structured_data_enabled === false) ? null : $data,
        ];
    }

    public static function prompt(Prompt $prompt): array
    {
        $prompt->loadMissing(['seo.ogMedia','featuredMedia']);
        $seo = $prompt->seo;
        $title = $prompt->title;
        $url = self::canonical($seo?->canonical_path, '/prompts/'.$prompt->slug);
        $image = self::image($seo?->ogMedia, $prompt->featuredMedia);
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $title,
            'description' => self::description($seo?->meta_description, $prompt->summary),
            'url' => $url,
        ];
        if ($image) $data['image'] = $image;

        // Do not claim offers, reviews, inventory or transactions that are not verified.
        return [
            'title' => self::title($seo?->seo_title, $title),
            'description' => self::description($seo?->meta_description, $prompt->summary),
            'canonical' => $url,
            'og_title' => self::title($seo?->og_title, $title),
            'og_description' => self::description($seo?->og_description, $seo?->meta_description ?: $prompt->summary),
            'og_type' => 'product',
            'image' => $image,
            'index' => $prompt->status === 'published' && ($seo?->robots_index ?? true),
            'schema' => $prompt->status === 'published' &&
                ($seo?->structured_data_enabled !== false) ? $data : null,
        ];
    }

    private static function title(?string $override, string $fallback): string
    {
        return Str::limit(trim((string) ($override ?: $fallback)), 160, '');
    }

    private static function description(?string $override, ?string $fallback): string
    {
        return Str::limit(trim(strip_tags((string) ($override ?: $fallback ?: ''))), 300, '');
    }

    private static function canonical(?string $path, string $fallbackPath): string
    {
        if ($path && preg_match('~^/(?:pages|blog|prompts)/[a-z0-9]+(?:-[a-z0-9]+)*$~D', $path)) {
            return url($path);
        }
        return url($fallbackPath);
    }

    private static function image(?object $custom, ?object $featured): ?string
    {
        $asset = $custom?->kind === 'image' ? $custom :
            ($featured?->kind === 'image' ? $featured : null);
        return $asset?->file_path ? route('media.serve', $asset) : null;
    }
}
