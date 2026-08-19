<?php

namespace App\Console\Commands;

use App\Content\PageContentStore;
use App\Models\Page;
use Illuminate\Console\Command;

/**
 * Puts the metadata in the seed files onto a site that already has content.
 *
 * `resources/content/pages/*.json` is the source of truth for a fresh install, and a live site is not
 * one: its pages exist, its editors have changed them, and `ContentSeeder` is `updateOrCreate` across
 * the *whole* page — slug, title, sections and all. So the obvious way to apply a metadata change to
 * production, `php artisan db:seed`, would overwrite every page with the version in the repository and
 * silently undo months of editing. That is why this exists.
 *
 * It writes **only the `seo` key**, through `PageContentStore::saveDetails()`, which merges rather than
 * replaces — so a page's sections, its title, its slug and any SEO field the seed file does not mention
 * are left exactly as they are. The narrowest write that does the job.
 *
 * Reports unless told to act, and run by hand rather than scheduled: the same shape as
 * `content:purge-deleted` and `enquiries:purge`, for the same reason. This one is not destructive, but
 * it changes what every search result says about the site, and that is somebody's decision.
 */
class ApplySeoMetadata extends Command
{
    protected $signature = 'seo:apply {--force : Actually write, rather than reporting}';

    protected $description = 'Apply the titles and descriptions in the content files to pages that already exist';

    public function handle(PageContentStore $store): int
    {
        $force = (bool) $this->option('force');
        $changed = 0;
        $missing = 0;

        foreach (glob(resource_path('content/pages/*.json')) ?: [] as $path) {
            $document = json_decode(file_get_contents($path), true);
            $slug = $document['slug'] ?? null;
            $wanted = $this->wanted($document['seo'] ?? []);

            if ($slug === null || $wanted === []) {
                continue;
            }

            $page = Page::where('slug', $slug)->first(['seo']);

            if ($page === null) {
                $this->line("  <fg=yellow>skip</>  {$slug} — not on this site");
                $missing++;

                continue;
            }

            $differences = $this->differences($page->seo ?? [], $wanted);

            if ($differences === []) {
                continue;
            }

            $changed++;

            foreach ($differences as $field => [$from, $to]) {
                $this->line("  <fg=cyan>{$slug}</>  {$field}");
                $this->line('      was: '.($from === null ? '<fg=gray>nothing</>' : $from));
                $this->line("      now: {$to}");
            }

            if ($force) {
                $store->saveDetails($slug, null, $wanted, 'seo:apply');
            }
        }

        $this->newLine();

        if ($changed === 0) {
            $this->info($missing > 0
                ? "Nothing to change. {$missing} page(s) in the content files are not on this site."
                : 'Nothing to change — every page already carries the metadata in the content files.');

            return self::SUCCESS;
        }

        if (! $force) {
            $this->comment("{$changed} page(s) would change. Run with --force to write them.");

            return self::SUCCESS;
        }

        $this->info("Updated the metadata on {$changed} page(s). Sections and titles were not touched.");

        return self::SUCCESS;
    }

    /**
     * Only the two fields this command owns. The sharing image and the canonical are per-page decisions
     * an editor makes in the builder, and a seed file that happens not to mention one must not be read
     * as an instruction to clear it.
     *
     * @param  array<string, mixed>  $seo
     * @return array<string, string>
     */
    private function wanted(array $seo): array
    {
        return array_filter([
            'title' => trim((string) ($seo['title'] ?? '')) ?: null,
            'description' => trim((string) ($seo['description'] ?? '')) ?: null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, string>  $wanted
     * @return array<string, array{0: string|null, 1: string}>
     */
    private function differences(array $current, array $wanted): array
    {
        $differences = [];

        foreach ($wanted as $field => $value) {
            $existing = trim((string) ($current[$field] ?? '')) ?: null;

            if ($existing !== $value) {
                $differences[$field] = [$existing, $value];
            }
        }

        return $differences;
    }
}
