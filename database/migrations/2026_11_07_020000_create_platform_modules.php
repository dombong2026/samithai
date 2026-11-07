<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title', 160); $t->string('kind', 15)->index();
            $t->string('mime', 80)->nullable(); $t->string('file_path', 255)->nullable();
            $t->unsignedBigInteger('bytes')->default(0); $t->string('checksum', 64)->nullable();
            $t->string('youtube_id', 20)->nullable(); $t->string('color_hex', 7)->nullable();
            $t->timestamps(); $t->index(['user_id', 'created_at']);
        });
        Schema::table('prompts', function (Blueprint $t) {
            $t->foreignId('featured_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
        });
        Schema::create('content_entries', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('kind', 16)->index(); $t->string('title', 160);
            $t->string('slug', 180); $t->text('excerpt')->nullable();
            $t->longText('body_html'); $t->string('status', 20)->default('draft')->index();
            $t->foreignId('featured_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $t->timestamp('published_at')->nullable(); $t->timestamps();
            $t->unique(['kind', 'slug']); $t->index(['kind', 'status']);
        });
        Schema::create('footer_columns', function (Blueprint $t) {
            $t->id(); $t->string('title', 80); $t->boolean('enabled')->default(true);
            $t->unsignedTinyInteger('position')->default(1); $t->timestamps();
        });
        Schema::create('footer_links', function (Blueprint $t) {
            $t->id(); $t->foreignId('footer_column_id')->constrained()->cascadeOnDelete();
            $t->string('label', 100); $t->string('url', 500);
            $t->unsignedSmallInteger('position')->default(1); $t->timestamps();
        });
        Schema::create('support_tickets', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('subject', 160); $t->string('status', 15)->default('open')->index();
            $t->timestamps(); $t->index(['user_id', 'created_at']);
        });
        Schema::create('support_messages', function (Blueprint $t) {
            $t->id(); $t->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->text('body'); $t->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 100); $t->string('subject_type', 80)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('ip_hash', 64)->nullable(); $t->json('details')->nullable(); $t->timestamp('created_at')->useCurrent();
            $t->index(['action', 'created_at']);
        });
        Schema::create('payment_transactions', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->string('provider', 50); $t->string('provider_reference', 160);
            $t->string('status', 24)->default('unverified'); $t->char('currency', 3)->default('USD');
            $t->decimal('amount', 12, 2); $t->timestamp('verified_at')->nullable();
            $t->timestamps(); $t->unique(['provider', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('footer_links');
        Schema::dropIfExists('footer_columns');
        Schema::dropIfExists('content_entries');
        Schema::table('prompts', fn (Blueprint $t) => $t->dropConstrainedForeignId('featured_media_id'));
        Schema::dropIfExists('media_assets');
    }
};
