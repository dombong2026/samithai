<?php

namespace Tests\Feature;

use App\Jobs\WorkerProbeJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueWorkerProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_queue_cannot_claim_worker_acceptance(): void
    {
        config(['queue.default' => 'sync']);
        $this->assertSame(2, Artisan::call('samith:queue-probe', ['--dispatch' => true]));
        $this->assertDatabaseCount('worker_probes', 0);
    }

    public function test_one_probe_requires_actual_handling_not_just_dispatch(): void
    {
        Queue::fake();
        config(['queue.default' => 'database']);

        $this->assertSame(0, Artisan::call('samith:queue-probe', ['--dispatch' => true]));
        $id = DB::table('worker_probes')->value('id');
        $this->assertTrue(Str::isUuid($id));
        Queue::assertPushed(WorkerProbeJob::class, 1);

        $this->assertSame(1, Artisan::call('samith:queue-probe', ['--check' => $id]));
        (new WorkerProbeJob($id))->handle();
        $this->assertSame(0, Artisan::call('samith:queue-probe', ['--check' => $id]));
    }

    public function test_invalid_or_missing_probe_never_passes(): void
    {
        config(['queue.default' => 'database']);
        $this->assertSame(2, Artisan::call('samith:queue-probe', ['--check' => 'not-a-uuid']));
        $this->assertSame(1, Artisan::call('samith:queue-probe', ['--check' => (string) Str::uuid()]));
    }
}
