<?php

use App\Models\Enquiry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two things `handled_at` could not say.
     *
     * It was one boolean wearing a timestamp: dealt with, or not. There was no way to show you had
     * picked something up without claiming it was finished, so anything half-done looked untouched.
     * `status` carries the three states instead, and `handled_at` goes rather than staying beside it
     * — two columns that can disagree, with no rule saying which wins, is how a screen ends up
     * reporting one thing and a count another.
     *
     * `read_at` is a different question and deliberately not the same column. It records that
     * somebody opened the enquiry, which is what the header's counter reports. Read is not the same
     * as answered, which is exactly why the sidebar counts `status` and the bell counts this — a
     * badge you can clear by glancing at something must never be the one saying how much work is
     * left. It is inbox-wide rather than per account: this is a shared inbox, and once a colleague
     * has read an enquiry it has been seen.
     */
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->string('status')->default(Enquiry::NEW)->after('page_slug')->index();
            $table->timestamp('status_changed_at')->nullable()->after('status');
            $table->timestamp('read_at')->nullable()->after('status_changed_at');
        });

        DB::table('enquiries')->whereNotNull('handled_at')->update([
            'status' => Enquiry::DEALT_WITH,
            'status_changed_at' => DB::raw('handled_at'),
        ]);

        /* `read_at` is left null for everything already here. They have all been seen in practice,
           but claiming a reading that was never recorded is worse than one round of counting them
           as new. */
        Schema::table('enquiries', fn (Blueprint $table) => $table->dropColumn('handled_at'));
    }

    public function down(): void
    {
        Schema::table('enquiries', fn (Blueprint $table) => $table->timestamp('handled_at')->nullable()->after('page_slug'));

        DB::table('enquiries')->where('status', Enquiry::DEALT_WITH)->update([
            'handled_at' => DB::raw('coalesce(status_changed_at, updated_at)'),
        ]);

        Schema::table('enquiries', fn (Blueprint $table) => $table->dropColumn(['status', 'status_changed_at', 'read_at']));
    }
};
