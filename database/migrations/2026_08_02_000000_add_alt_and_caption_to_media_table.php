<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A default description for the image itself, so nobody starts from an empty field. A placement
     * still keeps its own alt in the section tree and overrides this — the same photo reads
     * differently in a hero and beside a quote.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('alt')->nullable()->after('name');
            $table->string('caption')->nullable()->after('alt');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['alt', 'caption']);
        });
    }
};
