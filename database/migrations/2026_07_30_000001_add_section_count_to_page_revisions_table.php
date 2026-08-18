<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_revisions', function (Blueprint $table) {
            $table->unsignedInteger('section_count')->default(0)->after('by');
        });

        DB::table('page_revisions')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('page_revisions')
                    ->where('id', $row->id)
                    ->update(['section_count' => count(json_decode($row->sections, true) ?: [])]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('page_revisions', function (Blueprint $table) {
            $table->dropColumn('section_count');
        });
    }
};
