<?php

namespace Tests\Unit;

use App\Support\LocalizationCoverage;
use PHPUnit\Framework\TestCase;

class LocalizationCoverageTest extends TestCase
{
    public function test_all_supported_locale_folders_have_structural_report_entries(): void
    {
        $result = LocalizationCoverage::inspect(dirname(__DIR__, 2));

        $this->assertSame(13, $result['locale_count']);
        $this->assertCount(13, $result['locales']);
        $this->assertSame('pending independent review', $result['native_language_approval']);
        $this->assertGreaterThan(0, $result['locales']['en']['expected_keys']);
    }

    public function test_missing_translations_are_reported_without_falsely_approving_languages(): void
    {
        $root = sys_get_temp_dir().'/samith-lang-fixture-'.bin2hex(random_bytes(5));
        mkdir($root.'/lang/en', 0700, true);
        mkdir($root.'/lang/km', 0700, true);
        file_put_contents($root.'/lang/en/ui.php', "<?php return ['hello'=>'Hello :name'];");
        file_put_contents($root.'/lang/en/account.php', "<?php return ['sign_in'=>'Sign in'];");
        file_put_contents($root.'/lang/km/ui.php', "<?php return ['hello'=>'សួស្តី'];");
        file_put_contents($root.'/lang/km/account.php', "<?php return [];");

        try {
            $result = LocalizationCoverage::inspect($root);
            $this->assertFalse($result['structurally_complete']);
            $this->assertContains('ui.hello', $result['locales']['km']['placeholder_mismatches']);
            $this->assertContains('account.sign_in', $result['locales']['km']['missing_keys']);
        } finally {
            foreach (['en/ui.php', 'en/account.php', 'km/ui.php', 'km/account.php'] as $path) {
                unlink($root.'/lang/'.$path);
            }
            rmdir($root.'/lang/en');
            rmdir($root.'/lang/km');
            rmdir($root.'/lang');
            rmdir($root);
        }
    }
}
