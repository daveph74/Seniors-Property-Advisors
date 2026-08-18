<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reusable_sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->index();
            $table->json('block');
            $table->string('created_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reusable_sections');
    }
};
