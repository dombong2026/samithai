<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('prompts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->string('slug', 190)->unique();
            $table->text('summary');
            $table->longText('content');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->string('license_type', 20)->default('regular');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['category_id', 'status']);
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'prompt_id']);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('number', 32)->unique();
            $table->string('status', 20)->default('completed');
            $table->char('currency', 3)->default('USD');
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_id')->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->string('license_type', 20)->default('regular');
            $table->timestamps();
        });

        Schema::create('prompt_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('license_type', 20)->default('regular');
            $table->timestamps();
            $table->unique(['user_id', 'prompt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_entitlements');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('prompts');
        Schema::dropIfExists('categories');
    }
};
