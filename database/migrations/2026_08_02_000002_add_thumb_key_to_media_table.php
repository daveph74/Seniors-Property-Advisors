<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The small copy the CMS browses with. Kept as a key on the row rather than derived from the
     * original's, so the original stays the only thing content ever points at.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('thumb_key')->nullable()->after('key');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('thumb_key');
        });
    }
};
