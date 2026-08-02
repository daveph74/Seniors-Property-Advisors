<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where an enquiry waits.
     *
     * Scope §12 puts the CRM and its field mappings outside the CMS, so nothing here tries to be
     * that: it is a record of what somebody typed and when, kept so an enquiry is never lost while
     * the integration is pending, and so SyncID can read or replay from it later.
     *
     * `handled_at` is the one piece of state, for whoever works through them in the meantime.
     */
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('suburb')->nullable();
            $table->text('message')->nullable();
            $table->boolean('consented')->default(false);
            $table->string('page_slug')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
