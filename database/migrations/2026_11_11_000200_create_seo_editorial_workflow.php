<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('seo_metadata', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_entry_id')->nullable()->unique()->constrained('content_entries')->cascadeOnDelete();
            $table->foreignId('prompt_id')->nullable()->unique()->constrained('prompts')->cascadeOnDelete();
            $table->string('seo_title', 160)->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->string('canonical_path', 500)->nullable();
            $table->string('og_title', 160)->nullable();
            $table->string('og_description', 300)->nullable();
            $table->foreignId('og_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->boolean('robots_index')->default(true);
            $table->boolean('structured_data_enabled')->default(true);
            $table->timestamps();
        });

        Schema::table('content_entries', function (Blueprint $table): void {
            $table->string('editorial_status', 24)->default('draft')->index();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('editorial_approved_at')->nullable();
            $table->string('editorial_approved_hash', 64)->nullable();
            $table->timestamp('scheduled_for')->nullable()->index();
        });

        Schema::create('content_editorial_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_entry_id')->constrained('content_entries')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('content_sha256', 64);
            $table->longText('snapshot');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['content_entry_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_editorial_revisions');
        Schema::table('content_entries', function (Blueprint $table): void {
            $table->dropForeign(['reviewer_id']);
            $table->dropColumn([
                'editorial_status','reviewer_id','editorial_approved_at',
                'editorial_approved_hash','scheduled_for',
            ]);
        });
        Schema::dropIfExists('seo_metadata');
    }
};
