<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_probes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestamp('queued_at');
            $table->timestamp('handled_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_probes');
    }
};
