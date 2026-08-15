<?php

use App\Models\Enquiry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A second form now writes this table, and it asks questions the first one does not.
     *
     * `source` says which form, and it is a column rather than a reading of `page_slug` for the
     * reason given on `Enquiry::SOURCES`: the slug is the address the form sat on, supplied by the
     * browser, and both forms can be sent from the same page. The inbox filters on this, so it is
     * indexed like `status`.
     *
     * `details` holds the answers somebody *picked* — property type, when they hope to sell, when to
     * ring, and the suburb the lookup resolved. They are not columns because they are null for every
     * contact-form enquiry and a new question would mean a new migration; they are not folded into
     * `message` because that column is the sender's own words, which the CMS list snippet and the
     * search palette both rely on. Stored as keys, never labels — re-wording an answer must not
     * require rewriting rows.
     */
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->string('source')->default(Enquiry::CONTACT_FORM)->after('page_slug')->index();
            $table->json('details')->nullable()->after('source');
        });

        /* Not a guess: until this migration the contact form was the only path that wrote here, so
           every row already present did come through it. The column default covers new rows; this
           says the same thing about the old ones rather than leaving them to a default nobody set. */
        DB::table('enquiries')->update(['source' => Enquiry::CONTACT_FORM]);
    }

    public function down(): void
    {
        Schema::table('enquiries', fn (Blueprint $table) => $table->dropColumn(['source', 'details']));
    }
};
