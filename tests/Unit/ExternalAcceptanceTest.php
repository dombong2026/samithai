<?php

namespace Tests\Unit;

use App\Support\ExternalAcceptance;
use PHPUnit\Framework\TestCase;

class ExternalAcceptanceTest extends TestCase
{
    private function pendingManifest(): array
    {
        $gates = [];
        foreach (ExternalAcceptance::GATES as $id) {
            $gates[$id] = ['status' => 'pending'];
        }

        return [
            'format' => ExternalAcceptance::FORMAT,
            'release_sha' => str_repeat('a', 40),
            'package_sha256' => str_repeat('b', 64),
            'gates' => $gates,
        ];
    }

    public function test_unapproved_external_evidence_fails_closed(): void
    {
        $result = ExternalAcceptance::evaluate($this->pendingManifest());
        $this->assertFalse($result['ready_for_independent_signoff']);
        $this->assertSame(0, $result['accepted_count']);
        $this->assertSame(count(ExternalAcceptance::GATES), $result['required_count']);
    }

    public function test_accepted_word_without_evidence_does_not_pass(): void
    {
        $manifest = $this->pendingManifest();
        $manifest['gates']['aapanel_clean_install']['status'] = 'accepted';
        $result = ExternalAcceptance::evaluate($manifest);

        $this->assertSame('incomplete', $result['gate_statuses']['aapanel_clean_install']);
        $this->assertNotEmpty($result['errors']);
        $this->assertFalse($result['ready_for_independent_signoff']);
    }

    public function test_extraneous_gate_or_invalid_release_identity_blocks_acceptance(): void
    {
        $manifest = $this->pendingManifest();
        $manifest['release_sha'] = 'not-a-git-sha';
        $manifest['gates']['unknown_approval'] = ['status' => 'accepted'];
        $result = ExternalAcceptance::evaluate($manifest);

        $this->assertFalse($result['ready_for_independent_signoff']);
        $this->assertGreaterThanOrEqual(2, count($result['errors']));
    }

    public function test_complete_record_is_structurally_valid_not_independently_verified(): void
    {
        $manifest = $this->pendingManifest();
        foreach (ExternalAcceptance::GATES as $id) {
            $manifest['gates'][$id] = [
                'status' => 'accepted',
                'reviewer' => 'CI structural fixture',
                'reviewed_at' => '2026-11-08T00:00:00Z',
                'evidence_sha256' => str_repeat('c', 64),
                'evidence_reference' => 'CI-STRUCTURE-ONLY',
            ];
        }
        $result = ExternalAcceptance::evaluate($manifest);
        $this->assertTrue($result['ready_for_independent_signoff']);
        $this->assertStringContainsString('do not verify', $result['notice']);
    }
}
