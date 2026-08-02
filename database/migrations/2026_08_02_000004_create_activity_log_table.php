<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who changed what, and when. Scope §13 asks for the actions, the person and the time — not a
     * comparison interface, and not the content itself.
     *
     * `subject_label` and `by_name` are copies, deliberately. A log entry has to stay readable
     * after the thing it describes is deleted and after the person who did it leaves; a join to a
     * row that no longer exists would leave the most interesting entries blank.
     */
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->foreignId('by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('by_name')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
