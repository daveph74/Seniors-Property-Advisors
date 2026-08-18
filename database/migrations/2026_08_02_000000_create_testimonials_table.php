<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('quote');
            $table->string('location')->nullable();
            $table->string('headline')->nullable();
            $table->string('image')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('featured')->default(false);
            $table->boolean('active')->default(false);

            /*
             * Scope §7 closes with a constraint, not a field: names and images may only be
             * published where the client has given permission. It cannot be validated — the
             * permission lives outside the system — so it is recorded instead, and nothing can go
             * live without it. Stored as a timestamp and a person rather than a boolean, because
             * "who said so, and when" is the part worth having if it is ever questioned.
             */
            $table->timestamp('consent_confirmed_at')->nullable();
            $table->string('consent_confirmed_by')->nullable();

            $table->string('last_updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
