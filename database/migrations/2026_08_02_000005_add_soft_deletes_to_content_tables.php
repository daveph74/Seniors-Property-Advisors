<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['blog_posts', 'faqs', 'testimonials'];

    /**
     * Scope §13 asks for restoring recently deleted content where practical. Deleting used to be
     * final, so a mis-click cost an article and the only recourse was a database backup.
     *
     * Three tables, deliberately. Pages cannot be deleted at all — they archive. Media is excluded
     * because deleting an image destroys the file in storage as well, and a restored row pointing
     * at a file that no longer exists is worse than an honest deletion. Categories are excluded
     * because deleting one re-files everything under it, and putting the category back would not
     * put those articles back.
     */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->softDeletes());
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropSoftDeletes());
        }
    }
};
