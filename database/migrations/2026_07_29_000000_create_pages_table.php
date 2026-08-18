<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cms_id')->unique();
            $table->string('slug')->unique();
            $table->string('url');
            $table->string('title');
            $table->string('status')->default('draft')->index();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->json('seo')->nullable();
            $table->json('draft')->nullable();
            $table->json('published')->nullable();
            $table->string('last_updated_by')->nullable();
            $table->string('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
