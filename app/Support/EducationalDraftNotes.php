<?php

namespace App\Support;

use App\Models\ContentEntry;
use JsonException;

final class EducationalDraftNotes
{
    private const FILES = [
        '02-business-and-teaching.json',
        '03-website-and-social.json',
        '04-quality-and-agents.json',
    ];

    /**
     * A non-public review brief for six education drafts.
     * Existing, potentially human-edited article content is never overwritten.
     */
    public static function forEntry(ContentEntry $entry): ?array
    {
        if ($entry->kind !== 'post') return null;

        foreach (self::FILES as $file) {
            $path = base_path('database/content/educational-blog-2026-11/'.$file);
            if (! is_file($path) || is_link($path)) continue;

            try {
                $group = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            foreach ($group['articles'] ?? [] as $record) {
                if (($record['slug'] ?? null) !== $entry->slug) continue;

                return [
                    'primary_keyword' => (string) ($record['primary_keyword'] ?? ''),
                    'audience' => (string) ($record['audience'] ?? ''),
                    'review_notes' => array_values(array_filter(
                        $record['review_notes'] ?? [], 'is_string'
                    )),
                    'matches_original_source' => $entry->title === ($record['title'] ?? null)
                        && $entry->excerpt === ($record['excerpt'] ?? null)
                        && $entry->body_html === SafeHtml::clean((string) ($record['body_html'] ?? '')),
                ];
            }
        }

        return null;
    }
}
