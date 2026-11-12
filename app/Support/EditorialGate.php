<?php

namespace App\Support;

use App\Models\ContentEditorialRevision;
use App\Models\ContentEntry;

final class EditorialGate
{
    public static function fingerprint(ContentEntry $entry): string
    {
        return hash('sha256', json_encode([
            'kind' => $entry->kind, 'slug' => $entry->slug,
            'title' => $entry->title, 'excerpt' => $entry->excerpt,
            'body_html' => $entry->body_html,
            'featured_media_id' => $entry->featured_media_id,
            // Public social images are part of the reviewed artifact.
            'og_media_id' => $entry->seo?->og_media_id,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public static function approved(ContentEntry $entry): bool
    {
        return in_array($entry->editorial_status, ['approved', 'scheduled'], true)
            && $entry->reviewer_id !== null
            && $entry->editorial_approved_at !== null
            && hash_equals((string) $entry->editorial_approved_hash, self::fingerprint($entry));
    }

    public static function requiresReview(ContentEntry $entry): bool
    {
        // Blog articles require review before first publication.
        // Preserve existing custom-page publishing; pages opt in by entering review.
        return $entry->kind === 'post' || ($entry->kind === 'page'
            && in_array($entry->editorial_status,
                ['in_review','changes_requested','approved','scheduled'], true));
    }

    public static function revision(ContentEntry $entry, ?int $actorId, string $action): void
    {
        ContentEditorialRevision::query()->create([
            'content_entry_id' => $entry->getKey(),
            'actor_id' => $actorId,
            'action' => $action,
            'content_sha256' => self::fingerprint($entry),
            'snapshot' => json_encode([
                'title' => $entry->title, 'excerpt' => $entry->excerpt,
                'body_html' => $entry->body_html, 'slug' => $entry->slug,
                'kind' => $entry->kind, 'featured_media_id' => $entry->featured_media_id,
                'status' => $entry->status, 'editorial_status' => $entry->editorial_status,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    public static function invalidate(ContentEntry $entry): void
    {
        $entry->forceFill([
            'editorial_status' => 'draft',
            'editorial_approved_at' => null,
            'editorial_approved_hash' => null,
            'scheduled_for' => null,
        ])->save();
    }
}
