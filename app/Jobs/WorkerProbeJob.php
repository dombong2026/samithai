<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/** A content-free marker. Processing proves one job ran; not permanent supervision. */
final class WorkerProbeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly string $probeId)
    {
    }

    public function handle(): void
    {
        DB::table('worker_probes')
            ->where('id', $this->probeId)
            ->whereNull('handled_at')
            ->update(['handled_at' => now()]);
    }
}
