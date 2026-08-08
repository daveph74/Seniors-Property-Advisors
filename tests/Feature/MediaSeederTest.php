<?php

namespace Tests\Feature;

use App\Models\Media;
use Database\Seeders\MediaSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The pictures the seeded pages point at.
 *
 * They used to be hotlinked from a stock photography CDN, so they were nobody's to change and
 * nothing checked they still resolved. Now they are ordinary media rows, and the thing worth
 * asserting is that the pages and the library agree — a page naming an address the library has
 * never heard of is a broken image nobody notices until a reader finds it.
 */
class MediaSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
    }

    public function test_it_writes_a_row_and_the_bytes_for_every_picture(): void
    {
        $this->seed(MediaSeeder::class);

        $this->assertGreaterThan(0, Media::count());

        foreach (Media::all() as $media) {
            Storage::disk('s3')->assertExists($media->key);

            $this->assertSame('image/jpeg', $media->mime);
            $this->assertGreaterThan(0, $media->width, "{$media->key} has no width");
            $this->assertGreaterThan(0, $media->height, "{$media->key} has no height");
        }
    }

    /** Idempotent on key, the way ContentSeeder is on slug. */
    public function test_seeding_twice_does_not_duplicate_anything(): void
    {
        $this->seed(MediaSeeder::class);
        $first = Media::count();

        $this->seed(MediaSeeder::class);

        $this->assertSame($first, Media::count());
    }

    /**
     * The assertion that actually protects a reader: every /media/ address written into the seeded
     * pages, at any depth of the section tree, resolves to something the library holds.
     */
    public function test_every_picture_the_pages_ask_for_exists(): void
    {
        $this->seed(MediaSeeder::class);

        $keys = Media::pluck('key')->all();
        $wanted = [];

        foreach (glob(resource_path('content/pages/*.json')) ?: [] as $path) {
            preg_match_all('#/media/([a-z0-9/._-]+)#i', (string) file_get_contents($path), $found);

            foreach ($found[1] as $key) {
                $wanted[$key] = basename($path);
            }
        }

        $this->assertNotEmpty($wanted, 'no page names a picture at all');

        foreach ($wanted as $key => $file) {
            $this->assertContains($key, $keys, "{$file} points at /media/{$key}, which nothing provides");
        }
    }

    /** The site-wide sharing default is a settings value rather than page content, so it is checked too. */
    public function test_the_default_sharing_picture_exists(): void
    {
        $this->seed(MediaSeeder::class);

        $site = json_decode((string) file_get_contents(resource_path('content/site.json')), true);
        $key = str_replace('/media/', '', (string) ($site['seo']['image'] ?? ''));

        $this->assertNotSame('', $key, 'the site has no default sharing picture');
        $this->assertDatabaseHas('media', ['key' => $key]);
    }

    /** No page should be reaching off this origin for a picture again. */
    public function test_no_page_hotlinks_a_picture_from_somebody_elses_host(): void
    {
        foreach (glob(resource_path('content/pages/*.json')) ?: [] as $path) {
            $this->assertStringNotContainsString(
                'unsplash.com',
                (string) file_get_contents($path),
                basename($path).' hotlinks a picture',
            );
        }
    }
}
