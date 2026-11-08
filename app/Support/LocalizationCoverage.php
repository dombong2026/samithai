<?php

namespace App\Support;

/**
 * Reports structural locale coverage only; native-language signoff is separate.
 */
final class LocalizationCoverage
{
    public const LOCALES = ['en', 'zh', 'ko', 'ja', 'de', 'km', 'vi', 'th', 'id', 'ms', 'ar', 'fr', 'es'];

    public static function inspect(string $root): array
    {
        $groups = ['ui', 'account'];
        $english = [];
        foreach ($groups as $group) {
            $reference = $root.'/lang/en/'.$group.'.php';
            $value = is_file($reference) ? require $reference : null;
            $english[$group] = is_array($value) ? $value : [];
        }

        $results = [];
        $structuralIssues = 0;

        foreach (self::LOCALES as $locale) {
            $row = [
                'missing_keys' => [], 'extra_keys' => [],
                'blank_keys' => [], 'placeholder_mismatches' => [],
                'identical_to_english_count' => 0, 'review' => 'Native-language review not verified',
            ];

            foreach ($groups as $group) {
                $path = $root.'/lang/'.$locale.'/'.$group.'.php';
                $value = is_file($path) ? require $path : null;
                $translations = is_array($value) ? $value : [];
                $source = $english[$group];

                foreach ($source as $key => $text) {
                    $name = $group.'.'.$key;
                    if (! array_key_exists($key, $translations)) {
                        $row['missing_keys'][] = $name;
                        continue;
                    }
                    $translated = $translations[$key];
                    if (! is_string($translated) || trim($translated) === '') {
                        $row['blank_keys'][] = $name;
                        continue;
                    }
                    if ($locale !== 'en' && $translated === $text) {
                        $row['identical_to_english_count']++;
                    }
                    if (self::placeholders((string) $text) !== self::placeholders($translated)) {
                        $row['placeholder_mismatches'][] = $name;
                    }
                }

                foreach (array_keys($translations) as $key) {
                    if (! array_key_exists($key, $source)) {
                        $row['extra_keys'][] = $group.'.'.$key;
                    }
                }
            }

            $row['expected_keys'] = array_sum(array_map('count', $english));
            $row['missing_count'] = count($row['missing_keys']);
            $row['extra_count'] = count($row['extra_keys']);
            $row['blank_count'] = count($row['blank_keys']);
            $row['placeholder_mismatch_count'] = count($row['placeholder_mismatches']);
            $structuralIssues += $row['missing_count'] + $row['extra_count'] +
                $row['blank_count'] + $row['placeholder_mismatch_count'];
            $results[$locale] = $row;
        }

        return [
            'structurally_complete' => $structuralIssues === 0 && count($english['ui']) > 0 && count($english['account']) > 0,
            'structural_issue_count' => $structuralIssues,
            'locale_count' => count(self::LOCALES),
            'locales' => $results,
            'native_language_approval' => 'pending independent review',
        ];
    }

    private static function placeholders(string $text): array
    {
        preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*|%(?:[0-9]+\$)?[sd]/', $text, $matches);
        $result = $matches[0] ?? [];
        sort($result);
        return $result;
    }
}
