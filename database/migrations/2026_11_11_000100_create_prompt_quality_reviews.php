<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_quality_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prompt_id')->constrained('prompts')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('result', 20);
            $table->string('model_name', 120);
            $table->string('model_version', 120)->nullable();
            $table->text('test_scenario');
            $table->text('findings');
            $table->json('checks');
            $table->char('content_sha256', 64);
            $table->timestamps();
            $table->index(['prompt_id', 'created_at']);
            $table->index(['prompt_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_quality_reviews');
    }
};
