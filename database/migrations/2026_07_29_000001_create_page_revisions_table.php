<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('n');
            $table->string('action')->default('publish');
            $table->string('by');
            $table->json('sections');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->timestamps();

            $table->unique(['page_id', 'n']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_revisions');
    }
};
