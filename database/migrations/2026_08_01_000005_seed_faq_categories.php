<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The groupings scope §6 names. They are described there as examples, so every one of them
     * is renameable, reorderable, disableable and deletable in the CMS — this only saves the
     * client starting from an empty list.
     */
    private const CATEGORIES = [
        'General',
        'Selling',
        'Downsizing',
        'Fees',
        'The advisory process',
        'Property agents',
        'Legal and financial considerations',
    ];

    /**
     * Matched on name, so a category the client has already created or renamed to one of these
     * is left exactly as it is and never duplicated.
     */
    public function up(): void
    {
        $order = (int) DB::table('faq_categories')->max('sort_order');

        foreach (self::CATEGORIES as $name) {
            if (DB::table('faq_categories')->where('name', $name)->exists()) {
                continue;
            }

            DB::table('faq_categories')->insert([
                'name' => $name,
                'sort_order' => ++$order,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Only removes the ones it added, and only while no question is filed under them.
     */
    public function down(): void
    {
        foreach (self::CATEGORIES as $name) {
            $id = DB::table('faq_categories')->where('name', $name)->value('id');

            if ($id === null || DB::table('faqs')->where('faq_category_id', $id)->exists()) {
                continue;
            }

            DB::table('faq_categories')->where('id', $id)->delete();
        }
    }
};
