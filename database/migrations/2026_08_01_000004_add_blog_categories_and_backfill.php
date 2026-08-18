<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CATEGORIES = [
        ['name' => 'Property Advice', 'slug' => 'property-advice', 'sort_order' => 10],
        ['name' => 'Uncategorised', 'slug' => 'uncategorised', 'sort_order' => 999],
    ];

    /**
     * Adds the two categories and gives every article that has none a home, so "no category"
     * stops being a state the rest of the code has to handle.
     *
     * Idempotent on slug: re-running adds nothing and renames nothing, so a category the client
     * has already relabelled in the CMS keeps its name.
     */
    public function up(): void
    {
        foreach (self::CATEGORIES as $category) {
            if (DB::table('blog_categories')->where('slug', $category['slug'])->exists()) {
                continue;
            }

            DB::table('blog_categories')->insert($category + [
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $fallback = DB::table('blog_categories')->where('slug', 'uncategorised')->value('id');

        $orphans = DB::table('blog_posts')
            ->whereNotExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from('blog_category_post')
                ->whereColumn('blog_category_post.blog_post_id', 'blog_posts.id'))
            ->pluck('id');

        foreach ($orphans as $id) {
            DB::table('blog_category_post')->insert([
                'blog_post_id' => $id,
                'blog_category_id' => $fallback,
            ]);
        }
    }

    /**
     * Only removes what it added, and only while nothing is filed under them — dropping a
     * category takes its articles' filing with it.
     */
    public function down(): void
    {
        foreach (self::CATEGORIES as $category) {
            $id = DB::table('blog_categories')->where('slug', $category['slug'])->value('id');

            if ($id === null || DB::table('blog_category_post')->where('blog_category_id', $id)->exists()) {
                continue;
            }

            DB::table('blog_categories')->where('id', $id)->delete();
        }
    }
};
