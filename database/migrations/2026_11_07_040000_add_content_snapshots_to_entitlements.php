<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('prompt_entitlements', function (Blueprint $t) {
            $t->longText('content_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('prompt_entitlements', fn (Blueprint $t) => $t->dropColumn('content_snapshot'));
    }
};
