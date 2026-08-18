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
     * Matched on name, so a category the client has already created or renamed to one of these is
     * never duplicated — it is adopted, and moved into the order §6 lists them in. Anything the
     * client added beyond the seven keeps its relative order, after them.
     *
     * Bails out entirely once all seven exist, so a re-run cannot undo a reorder made in the CMS.
     */
    public function up(): void
    {
        $existing = DB::table('faq_categories')->pluck('id', 'name');

        if (count(array_intersect(self::CATEGORIES, $existing->keys()->all())) === count(self::CATEGORIES)) {
            return;
        }

        foreach (array_values(self::CATEGORIES) as $index => $name) {
            $order = $index + 1;

            if ($existing->has($name)) {
                DB::table('faq_categories')
                    ->where('id', $existing[$name])
                    ->update(['sort_order' => $order, 'updated_at' => now()]);

                continue;
            }

            DB::table('faq_categories')->insert([
                'name' => $name,
                'sort_order' => $order,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('faq_categories')
            ->whereNotIn('name', self::CATEGORIES)
            ->update(['sort_order' => DB::raw('sort_order + '.count(self::CATEGORIES))]);
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
