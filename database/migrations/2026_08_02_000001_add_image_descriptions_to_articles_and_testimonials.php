<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Both of these render a real photograph with `alt=""` today, and there was nowhere to type
     * anything else. A section in the builder has carried a per-placement description all along;
     * these two live outside the section tree and were missed.
     */
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('featured_image_alt')->nullable()->after('featured_image');
        });

        Schema::table('testimonials', function (Blueprint $table) {
            $table->string('image_alt')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn('featured_image_alt');
        });

        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn('image_alt');
        });
    }
};
