<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('legal_versions', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32)->index();
            $table->string('version', 32);
            $table->string('title', 160);
            $table->longText('body_html');
            $table->string('status', 16)->default('draft')->index();
            $table->string('review_reference', 255)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamps();
            $table->unique(['kind','version']);
        });
        Schema::create('legal_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('legal_version_id')->constrained('legal_versions')->restrictOnDelete();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('accepted_at');
            $table->unique(['order_id','legal_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_acceptances');
        Schema::dropIfExists('legal_versions');
    }
};
