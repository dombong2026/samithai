<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('prompt_entitlements', function (Blueprint $t) {
            $t->longText('content_snapshot')->nullable();
        });

        // Existing zero-value entitlements are frozen at migration time.
        // Do not invalidate previously issued customer library access.
        DB::table('prompt_entitlements')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                $text = DB::table('prompts')->where('id',$row->prompt_id)->value('content');
                if (is_string($text)) {
                    DB::table('prompt_entitlements')->where('id',$row->id)
                        ->whereNull('content_snapshot')->update(['content_snapshot'=>$text]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('prompt_entitlements', fn (Blueprint $t) => $t->dropColumn('content_snapshot'));
    }
};
