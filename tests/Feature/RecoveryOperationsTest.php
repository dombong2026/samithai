<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Support\OperationsMonitor;
use App\Support\RecoveryInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecoveryOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_inventory_catches_row_id_changes_without_exposing_customer_data(): void
    {
        $before = RecoveryInventory::capture();
        $this->assertTrue(RecoveryInventory::healthy($before));
        $this->assertSame([], RecoveryInventory::differences($before, $before));

        User::factory()->create(['name' => 'Private Customer Identity']);
        $after = RecoveryInventory::capture();

        $this->assertContains('users.count', RecoveryInventory::differences($before, $after));
        $this->assertContains('users.id_sha256', RecoveryInventory::differences($before, $after));
        $this->assertStringNotContainsString('Private Customer Identity', json_encode($after));
    }

    public function test_recovery_inventory_detects_content_tampering_without_id_changes(): void
    {
        $person = User::factory()->create(['name' => 'Original Name']);
        $before = RecoveryInventory::capture();

        $person->update(['name' => 'Modified Name']);
        $after = RecoveryInventory::capture();

        $this->assertSame($before['tables']['users']['count'], $after['tables']['users']['count']);
        $this->assertSame($before['tables']['users']['id_sha256'], $after['tables']['users']['id_sha256']);
        $this->assertContains('users.content_hmac_sha256', RecoveryInventory::differences($before, $after));
    }

    public function test_recovery_inventory_detects_missing_and_changed_private_media(): void
    {
        Storage::fake('local');
        $person = User::factory()->create();
        $file = 'media/'.$person->id.'/fixture.png';
        Storage::disk('local')->put($file, 'fixture-image-bytes');

        MediaAsset::create([
            'user_id' => $person->id,
            'title' => 'Recovery fixture',
            'kind' => 'image',
            'mime' => 'image/png',
            'file_path' => $file,
            'bytes' => 19,
            'checksum' => hash('sha256', 'fixture-image-bytes'),
        ]);

        $before = RecoveryInventory::capture();
        $this->assertTrue(RecoveryInventory::healthy($before));
        $this->assertSame(1, $before['media']['referenced']);

        Storage::disk('local')->put($file, 'changed bytes');
        $corrupt = RecoveryInventory::capture();
        $this->assertSame(1, $corrupt['media']['checksum_mismatch']);
        $this->assertContains('media.checksum_mismatch', RecoveryInventory::differences($before, $corrupt));

        Storage::disk('local')->delete($file);
        $missing = RecoveryInventory::capture();
        $this->assertSame(1, $missing['media']['missing']);
        $this->assertFalse(RecoveryInventory::healthy($missing));
    }

    public function test_database_queue_monitor_reports_backlogs_and_failed_jobs_without_payloads(): void
    {
        config()->set('queue.default', 'database');
        $now = now()->timestamp;
        DB::table('jobs')->insert([
            'queue' => 'default', 'payload' => 'PRIVATE_JOB_PAYLOAD',
            'attempts' => 0, 'reserved_at' => null,
            'available_at' => $now - 60, 'created_at' => $now - 90,
        ]);
        DB::table('jobs')->insert([
            'queue' => 'default', 'payload' => 'PRIVATE_RESERVED_PAYLOAD',
            'attempts' => 2, 'reserved_at' => $now - 500,
            'available_at' => $now - 600, 'created_at' => $now - 800,
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
            'payload' => 'PRIVATE_FAILED_PAYLOAD', 'exception' => 'PRIVATE_EXCEPTION',
            'failed_at' => now(),
        ]);

        $report = OperationsMonitor::inspect();

        $this->assertSame('1', $report['metrics']['Ready queue jobs']);
        $this->assertSame('1', $report['metrics']['Stale reserved queue jobs']);
        $this->assertSame('1', $report['metrics']['Failed queue jobs']);
        $this->assertSame('review', $report['checks']['queue_backlog']['status']);
        $this->assertSame('review', $report['checks']['failed_jobs']['status']);
        $this->assertSame('review', $report['checks']['worker_evidence']['status']);
        $this->assertStringNotContainsString('PRIVATE_', json_encode($report));
    }

    public function test_synchronous_queue_does_not_claim_worker_evidence(): void
    {
        config()->set('queue.default', 'sync');

        $report = OperationsMonitor::inspect();
        $this->assertSame('review', $report['checks']['queue_driver']['status']);
        $this->assertSame('review', $report['checks']['queue_tables']['status']);
    }

    public function test_recovery_cli_reports_non_sensitive_data_and_strict_accepts_clean_database(): void
    {
        User::factory()->create(['email' => 'private-recovery@example.test']);
        $status = Artisan::call('samith:recovery-inventory', [
            '--json' => true, '--strict' => true,
        ]);
        $output = Artisan::output();
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $status);
        $this->assertSame(RecoveryInventory::FORMAT, $decoded['format']);
        $this->assertStringNotContainsString('private-recovery@example.test', $output);
        $this->assertArrayHasKey('media', $decoded);
    }

    public function test_installer_page_is_never_cacheable(): void
    {
        $this->get(route('installer.show'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
