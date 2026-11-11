<?php

namespace App\Support;

use App\Models\Prompt;
use App\Models\PromptQualityReview;

final class PromptQualityGate
{
    /**
     * The original Starter 30 are subject to manual test review before submission.
     * Older marketplace products keep their existing moderation rules.
     */
    public static function requiresReview(Prompt $prompt): bool
    {
        return str_starts_with($prompt->slug, 'starter-2026-');
    }

    /**
     * A review is bound to the exact listing and private prompt text, not just
     * an ID. Revision of any substantive field invalidates old passing tests.
     */
    public static function fingerprint(Prompt $prompt): string
    {
        return hash('sha256', json_encode([
            $prompt->title, $prompt->summary, $prompt->content,
            $prompt->category_id, (string) $prompt->price,
            $prompt->license_type,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public static function latest(Prompt $prompt): ?PromptQualityReview
    {
        return $prompt->qualityReviews()->with('reviewer:id,name,role')
            ->orderByDesc('id')->first();
    }

    public static function passes(Prompt $prompt): bool
    {
        if (!self::requiresReview($prompt)) {
            return true;
        }

        $latest = self::latest($prompt);

        return $latest !== null
            && $latest->result === 'passed'
            && $latest->content_sha256 === self::fingerprint($prompt)
            && $latest->reviewer?->isAdmin() === true
            && collect($latest->checks)->every(fn ($value) => $value === true)
            && count($latest->checks ?? []) === 4;
    }

    public static function status(Prompt $prompt): string
    {
        $latest = self::latest($prompt);

        if (!$latest) return 'Not tested';
        if ($latest->content_sha256 !== self::fingerprint($prompt)) return 'Revision needs retest';

        return $latest->result === 'passed' && self::passes($prompt)
            ? 'Passed human QA'
            : 'Needs revision';
    }
}
