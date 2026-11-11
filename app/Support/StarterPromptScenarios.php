<?php

namespace App\Support;

use App\Models\Prompt;
use Illuminate\Support\Str;

/**
 * Editorial testing notes are kept in the Git-tracked starter JSON and are
 * never disclosed to anonymous visitors or attached to published listings.
 */
final class StarterPromptScenarios
{
    private const FILES = [
        '01-marketing-social-media.json',
        '02-education-teaching.json',
        '03-business-productivity.json',
        '04-coding-development.json',
        '05-ai-image-video.json',
        '06-ecommerce-sales.json',
        '07-data-research.json',
    ];

    public static function forPrompt(Prompt $prompt): ?array
    {
        if (!PromptQualityGate::requiresReview($prompt)) return null;

        foreach (self::FILES as $filename) {
            $file = base_path('database/content/starter-2026-11/'.$filename);
            if (!is_file($file) || is_link($file)) continue;
            $data = json_decode((string) file_get_contents($file), true);
            foreach (($data['products'] ?? []) as $item) {
                if (($item['slug'] ?? null) === $prompt->slug) {
                    return [
                        'scenario' => (string) ($item['review_scenario'] ?? ''),
                        'checks' => array_values(array_filter(
                            $item['review_checks'] ?? [],
                            fn ($value) => is_string($value)
                        )),
                    ];
                }
            }
        }

        return null;
    }
}
