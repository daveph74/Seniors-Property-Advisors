<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('nav_label')->nullable()->after('title');
        });

        Schema::create('page_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_url')->unique();
            $table->string('to_url');
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_redirects');

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('nav_label');
        });
    }
};
