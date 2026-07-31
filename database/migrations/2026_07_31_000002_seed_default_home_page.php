<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The home page is the only route the site cannot render without content, so a
     * fresh install must not depend on someone remembering to run the seeders. The
     * source stays resources/content/pages/home.json — the same file ContentSeeder
     * reads — rather than a copy pasted in here, so the two cannot drift.
     *
     * This only ever fills an empty home page. If the page already holds a draft or
     * published sections it is left alone, so re-running migrations on a live
     * database can never overwrite edited content.
     */
    public function up(): void
    {
        $document = $this->defaultHome();

        if ($document === null || $this->alreadyHasContent()) {
            return;
        }

        $row = [
            'cms_id' => $document['cmsId'] ?? 1,
            'slug' => 'home',
            'url' => $document['url'] ?? '/',
            'title' => $document['title'] ?? 'Home',
            'status' => $document['status'] ?? 'published',
            'seo' => json_encode($document['seo'] ?? []),
            'draft' => $document['draft'] === null ? null : json_encode($document['draft'] ?? null),
            'published' => json_encode($document['published'] ?? []),
            'last_updated_by' => $document['last_updated_by'] ?? null,
            'published_by' => $document['published_by'] ?? null,
            'published_at' => $document['published_at'] ?? null,
            'updated_at' => now(),
        ];

        if (DB::table('pages')->where('slug', 'home')->exists()) {
            DB::table('pages')->where('slug', 'home')->update($row);

            return;
        }

        DB::table('pages')->insert($row + ['created_at' => now()]);
    }

    /**
     * Deliberately does nothing. Rolling a migration back should not delete the
     * website's content.
     */
    public function down(): void {}

    private function defaultHome(): ?array
    {
        $path = resource_path('content/pages/home.json');

        if (! is_file($path)) {
            return null;
        }

        $document = json_decode((string) file_get_contents($path), true);

        return is_array($document) && ($document['slug'] ?? null) === 'home' ? $document : null;
    }

    private function alreadyHasContent(): bool
    {
        $page = DB::table('pages')->where('slug', 'home')->first();

        if ($page === null) {
            return false;
        }

        $published = json_decode($page->published ?? '[]', true);
        $draft = json_decode($page->draft ?? 'null', true);

        return (is_array($published) && $published !== []) || (is_array($draft) && $draft !== []);
    }
};
