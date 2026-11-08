<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prompt_id')->constrained('prompts')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['prompt_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_reviews');
    }
};
