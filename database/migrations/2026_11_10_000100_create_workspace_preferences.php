<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('context', 12);
            $table->json('shortcuts');
            $table->timestamps();
            $table->unique(['user_id', 'context']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_preferences');
    }
};
