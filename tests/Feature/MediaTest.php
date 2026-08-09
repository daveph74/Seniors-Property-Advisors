<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaTest extends TestCase
{
    /** Real bytes, because the upload path now identifies a file by what is in it. */
    private function image(int $width, int $height, string $format = 'png'): string
    {
        $canvas = imagecreatetruecolor($width, $height);

        imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocate($canvas, 40, 90, 150));

        ob_start();
        $format === 'jpg' ? imagejpeg($canvas, null, 92) : imagepng($canvas);
        $bytes = (string) ob_get_clean();

        imagedestroy($canvas);

        return $bytes;
    }

    private function record(array $overrides = []): Media
    {
        return Media::create(array_merge([
            'key' => '2026/07/abc123.png',
            'name' => 'photo.png',
            'mime' => 'image/png',
            'size' => 2048,
            'width' => 800,
            'height' => 600,
            'disk' => 's3',
        ], $overrides));
    }

    public function test_it_signs_an_upload_and_generates_the_key_itself(): void
    {
        Storage::fake('s3');

        $response = $this->postJson('/cms/media/sign', ['name' => 'My Photo.PNG', 'size' => 2048]);

        $response->assertOk();

        $key = $response->json('key');

        $this->assertMatchesRegularExpression('#^\d{4}/\d{2}/[0-9a-z]+\.png$#', $key);
        $this->assertStringNotContainsString('My Photo', $key);
        $this->assertSame('My-Photo.PNG', $response->json('name'));
    }

    public function test_it_refuses_to_sign_an_unsupported_type(): void
    {
        Storage::fake('s3');

        $this->postJson('/cms/media/sign', ['name' => 'script.php', 'size' => 100])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'not supported'));
    }

    public function test_it_refuses_to_sign_something_over_the_limit(): void
    {
        Storage::fake('s3');

        $this->postJson('/cms/media/sign', ['name' => 'huge.jpg', 'size' => 26214401])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, '25 MB'));
    }

    public function test_the_picker_library_lists_images_and_leaves_out_other_files(): void
    {
        Storage::fake('s3');

        $this->record(['key' => '2026/07/photo.png', 'name' => 'photo.png']);
        $this->record(['key' => '2026/07/deed.pdf', 'name' => 'deed.pdf', 'mime' => 'application/pdf']);

        $response = $this->getJson('/cms/media/library');

        $response->assertOk();
        $this->assertSame(['photo.png'], array_column($response->json('items'), 'name'));
    }

    public function test_the_picker_library_searches_by_name(): void
    {
        Storage::fake('s3');

        $this->record(['key' => '2026/07/garden.png', 'name' => 'garden-terrace.png']);
        $this->record(['key' => '2026/07/kitchen.png', 'name' => 'kitchen.png']);

        $items = $this->getJson('/cms/media/library?search=garden')->assertOk()->json('items');

        $this->assertSame(['garden-terrace.png'], array_column($items, 'name'));
    }

    public function test_the_picker_library_searches_what_is_in_the_picture_too(): void
    {
        Storage::fake('s3');

        $this->record([
            'key' => '2026/07/134021038407023939.jpg',
            'name' => '134021038407023939.jpg',
            'alt' => 'An advisor guiding a senior couple',
        ]);
        $this->record([
            'key' => '2026/07/other.png',
            'name' => 'other.png',
            'caption' => 'Taken in Geelong',
        ]);

        /* A camera filename tells an editor nothing; what is in the picture is what they remember. */
        $found = $this->getJson('/cms/media/library?search=couple')->json('items');
        $byCaption = $this->getJson('/cms/media/library?search=geelong')->json('items');

        $this->assertSame(['134021038407023939.jpg'], array_column($found, 'name'));
        $this->assertSame(['other.png'], array_column($byCaption, 'name'));
    }

    public function test_the_picker_library_caps_how_much_it_returns(): void
    {
        Storage::fake('s3');

        for ($i = 0; $i < 65; $i++) {
            $this->record(['key' => "2026/07/n{$i}.png", 'name' => "n{$i}.png"]);
        }

        $this->assertCount(60, $this->getJson('/cms/media/library')->json('items'));
    }

    public function test_it_refuses_to_sign_anything_that_is_not_an_image(): void
    {
        Storage::fake('s3');

        foreach (['deed.pdf', 'notes.txt', 'sheet.xlsx'] as $name) {
            $this->postJson('/cms/media/sign', ['name' => $name, 'size' => 2048])
                ->assertStatus(422)
                ->assertJsonPath('message', fn ($m) => str_contains($m, 'not supported'));
        }

        foreach (['photo.jpg', 'photo.PNG', 'icon.svg', 'shot.webp'] as $name) {
            $this->postJson('/cms/media/sign', ['name' => $name, 'size' => 2048])->assertOk();
        }
    }

    public function test_it_serves_files_with_headers_that_neutralise_active_content(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('2026/07/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');

        $media = $this->record(['key' => '2026/07/logo.svg', 'name' => 'logo.svg', 'mime' => 'image/svg+xml']);

        $this->get($media->url())
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'");
    }

    public function test_it_records_an_upload_that_arrived(): void
    {
        Storage::fake('s3');
        $bytes = $this->image(640, 480);
        Storage::disk('s3')->put('2026/07/landed.png', $bytes);

        $this->postJson('/cms/media', [
            'key' => '2026/07/landed.png',
            'name' => 'landed.png',
            'mime' => 'image/png',
            'width' => 640,
            'height' => 480,
        ])->assertCreated()
            ->assertJsonPath('url', '/media/2026/07/landed.png')
            ->assertJsonPath('size', strlen($bytes));

        $this->assertDatabaseHas('media', ['key' => '2026/07/landed.png', 'size' => strlen($bytes)]);
    }

    public function test_a_large_image_is_shrunk_in_place_and_keeps_its_address(): void
    {
        Storage::fake('s3');
        $original = $this->image(3000, 1500, 'jpg');
        Storage::disk('s3')->put('2026/08/big.jpg', $original);

        $this->postJson('/cms/media', [
            'key' => '2026/08/big.jpg',
            'name' => 'big.jpg',
            'mime' => 'image/jpeg',
            'width' => 3000,
            'height' => 1500,
        ])->assertCreated()
            /* Same key, so every reference to it and the usage scanner keep working. */
            ->assertJsonPath('url', '/media/2026/08/big.jpg')
            ->assertJsonPath('width', 2400)
            ->assertJsonPath('height', 1200);

        $stored = Storage::disk('s3')->get('2026/08/big.jpg');

        $this->assertLessThan(strlen($original), strlen($stored));
        $this->assertSame([2400, 1200], array_slice(getimagesizefromstring($stored), 0, 2));
        $this->assertSame(strlen($stored), Media::sole()->size);
    }

    public function test_an_upload_gets_a_small_copy_for_the_cms_to_browse_with(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('2026/08/portrait.jpg', $this->image(1000, 1500, 'jpg'));

        $response = $this->postJson('/cms/media', [
            'key' => '2026/08/portrait.jpg', 'name' => 'portrait.jpg', 'mime' => 'image/jpeg',
        ])->assertCreated();

        $thumb = $response->json('thumb');

        $this->assertSame('/media/thumbs/2026/08/portrait.jpg', $thumb);
        Storage::disk('s3')->assertExists('thumbs/2026/08/portrait.jpg');

        $small = Storage::disk('s3')->get('thumbs/2026/08/portrait.jpg');
        $full = Storage::disk('s3')->get('2026/08/portrait.jpg');

        /* The point of the exercise: the CMS grid stops pulling megabytes to fill a small box. */
        $this->assertLessThan(strlen($full), strlen($small));
        $this->assertSame(480, getimagesizefromstring($small)[1]);
    }

    public function test_a_small_copy_is_served_with_its_own_length(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('2026/08/a.png', $this->image(1000, 800));
        Storage::disk('s3')->put('thumbs/2026/08/a.png', $this->image(200, 160));

        $media = $this->record([
            'key' => '2026/08/a.png',
            'thumb_key' => 'thumbs/2026/08/a.png',
            'size' => 999999,
        ]);

        $this->get($media->thumbUrl())
            ->assertOk()
            ->assertHeader('Content-Length', (string) strlen(Storage::disk('s3')->get('thumbs/2026/08/a.png')));
    }

    public function test_deleting_an_image_takes_its_small_copy_with_it(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('2026/08/b.png', 'x');
        Storage::disk('s3')->put('thumbs/2026/08/b.png', 'x');

        $media = $this->record(['key' => '2026/08/b.png', 'thumb_key' => 'thumbs/2026/08/b.png']);

        $this->deleteJson("/cms/media/{$media->id}")->assertOk();

        Storage::disk('s3')->assertMissing('2026/08/b.png');
        Storage::disk('s3')->assertMissing('thumbs/2026/08/b.png');
    }

    public function test_an_image_with_no_small_copy_falls_back_to_itself(): void
    {
        $media = $this->record();

        $this->assertSame($media->url(), $media->thumbUrl());
    }

    public function test_an_image_already_small_enough_is_left_alone(): void
    {
        Storage::fake('s3');
        $original = $this->image(900, 600);
        Storage::disk('s3')->put('2026/08/small.png', $original);

        $this->postJson('/cms/media', [
            'key' => '2026/08/small.png', 'name' => 'small.png', 'mime' => 'image/png',
        ])->assertCreated()->assertJsonPath('width', 900);

        /* Byte for byte — re-encoding something that does not need it only loses quality. */
        $this->assertSame($original, Storage::disk('s3')->get('2026/08/small.png'));
    }

    public function test_the_recorded_size_and_type_come_from_the_bytes_not_the_request(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('2026/08/real.png', $this->image(300, 200));

        $this->postJson('/cms/media', [
            'key' => '2026/08/real.png',
            'name' => 'real.png',
            'mime' => 'image/nonsense',
            'width' => 9999,
            'height' => 9999,
        ])->assertCreated();

        $media = Media::sole();

        $this->assertSame('image/png', $media->mime);
        $this->assertSame([300, 200], [$media->width, $media->height]);
    }

    public function test_a_file_that_is_not_the_image_its_name_claims_is_refused(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('2026/08/liar.png', '<?php echo "not an image";');

        $this->postJson('/cms/media', [
            'key' => '2026/08/liar.png', 'name' => 'liar.png', 'mime' => 'image/png',
        ])->assertStatus(422);

        Storage::disk('s3')->assertMissing('2026/08/liar.png');
        $this->assertSame(0, Media::count());
    }

    public function test_an_image_saved_under_the_wrong_extension_is_refused(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('2026/08/mislabelled.png', $this->image(200, 200, 'jpg'));

        $this->postJson('/cms/media', [
            'key' => '2026/08/mislabelled.png', 'name' => 'mislabelled.png', 'mime' => 'image/png',
        ])->assertStatus(422);

        $this->assertSame(0, Media::count());
    }

    public function test_only_a_super_administrator_can_upload_an_svg(): void
    {
        Storage::fake('s3');

        $this->actingAs($this->clientAdmin());

        $this->postJson('/cms/media/sign', ['name' => 'logo.svg', 'size' => 2048])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only a super administrator can upload an SVG. Use a PNG or a JPG.');

        $this->actingAs($this->superAdmin());

        $this->postJson('/cms/media/sign', ['name' => 'logo.svg', 'size' => 2048])->assertOk();
    }

    public function test_a_plain_svg_is_recorded_and_keeps_its_artwork(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put(
            '2026/08/mark.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4"/></svg>',
        );

        $this->postJson('/cms/media', [
            'key' => '2026/08/mark.svg', 'name' => 'mark.svg', 'mime' => 'image/svg+xml',
        ])->assertCreated()->assertJsonPath('mime', 'image/svg+xml');

        $this->assertStringContainsString('<circle', Storage::disk('s3')->get('2026/08/mark.svg'));
    }

    /**
     * The one upload nothing used to look at. An SVG is a document that can carry script, and the
     * only thing standing between one and a reader was a response header surviving whatever sits
     * in front of the route.
     */
    public function test_an_svg_is_rewritten_without_its_script(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('2026/08/nasty.svg', <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg">
                <script>alert(1)</script>
                <circle cx="5" cy="5" r="4" onload="alert(2)"/>
                <a xlink:href="javascript:alert(3)"><text>x</text></a>
            </svg>
            SVG);

        $this->postJson('/cms/media', [
            'key' => '2026/08/nasty.svg', 'name' => 'nasty.svg', 'mime' => 'image/svg+xml',
        ])->assertCreated();

        $stored = Storage::disk('s3')->get('2026/08/nasty.svg');

        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onload', $stored);
        $this->assertStringNotContainsString('javascript:', $stored);
        $this->assertStringContainsString('<circle', $stored, 'the drawing itself survives');
    }

    /** Anything the sanitiser cannot vouch for is refused outright rather than stored. */
    public function test_an_svg_that_cannot_be_made_safe_is_refused_and_deleted(): void
    {
        Storage::fake('s3');

        $refused = [
            'foreign object' => '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><body xmlns="http://www.w3.org/1999/xhtml"><img src=x onerror="alert(1)"></body></foreignObject></svg>',
            'external entity' => '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>',
            'not an svg' => 'this is not markup at all',
            'empty file' => '',
        ];

        foreach ($refused as $label => $body) {
            $key = '2026/08/'.md5($label).'.svg';
            Storage::disk('s3')->put($key, $body);

            $this->postJson('/cms/media', ['key' => $key, 'name' => 'x.svg', 'mime' => 'image/svg+xml'])
                ->assertStatus(422);

            $this->assertFalse(Storage::disk('s3')->exists($key), "{$label} was left in storage");
        }

        $this->assertSame(0, Media::count());
    }

    public function test_a_description_and_caption_can_be_saved_against_an_image(): void
    {
        $media = $this->record();

        $this->patch("/cms/media/{$media->id}", [
            'alt' => 'An advisor <b>guiding</b> a couple',
            'caption' => 'Taken in Geelong',
        ])->assertRedirect();

        $media->refresh();

        $this->assertSame('An advisor guiding a couple', $media->alt);
        $this->assertSame('Taken in Geelong', $media->caption);
    }

    public function test_clearing_a_description_stores_nothing_rather_than_an_empty_string(): void
    {
        $media = $this->record(['alt' => 'Something']);

        $this->patch("/cms/media/{$media->id}", ['alt' => '   '])->assertRedirect();

        $this->assertNull($media->refresh()->alt);
    }

    public function test_it_rejects_a_record_whose_object_never_arrived(): void
    {
        Storage::fake('s3');

        $this->postJson('/cms/media', [
            'key' => '2026/07/ghost.png',
            'name' => 'ghost.png',
            'mime' => 'image/png',
        ])->assertStatus(422);

        $this->assertDatabaseCount('media', 0);
    }

    public function test_it_rejects_a_key_it_did_not_generate(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('elsewhere/evil.png', 'x');

        $this->postJson('/cms/media', [
            'key' => 'elsewhere/evil.png',
            'name' => 'evil.png',
            'mime' => 'image/png',
        ])->assertStatus(422);

        $this->assertDatabaseCount('media', 0);
    }

    public function test_it_streams_a_file_from_a_dated_key(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('2026/07/abc123.png', 'the-bytes');

        $media = $this->record(['size' => 9]);

        $response = $this->get($media->url());

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');

        $this->assertSame('the-bytes', $response->streamedContent());
    }

    public function test_an_unknown_key_is_not_found(): void
    {
        Storage::fake('s3');

        $this->get('/media/2026/07/nope.png')->assertNotFound();
    }

    public function test_a_record_without_its_object_is_not_found(): void
    {
        Storage::fake('s3');

        $media = $this->record();

        $this->get($media->url())->assertNotFound();
    }

    public function test_it_reports_storage_being_down_rather_than_crashing(): void
    {
        Storage::fake('s3');

        $media = $this->record(['disk' => 'unreachable']);

        $this->get($media->url())->assertStatus(503);
    }

    public function test_deleting_removes_the_object_and_the_row(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('2026/07/abc123.png', 'x');

        $media = $this->record();

        $this->deleteJson("/cms/media/{$media->id}")->assertOk();

        Storage::disk('s3')->assertMissing('2026/07/abc123.png');
        $this->assertDatabaseCount('media', 0);
    }

    private function pageUsing(string $url, string $column, string $title = 'Home'): Page
    {
        return Page::create([
            'cms_id' => Page::max('cms_id') + 1,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(4),
            'url' => '/'.Str::slug($title).'-'.Str::random(4),
            'status' => 'published',
            'draft' => $column === 'draft' ? [['id' => 'a', 'type' => 'hero', 'data' => ['image' => ['src' => $url]]]] : [],
            'published' => $column === 'published' ? [['id' => 'a', 'type' => 'hero', 'data' => ['image' => ['src' => $url]]]] : [],
        ]);
    }

    public function test_it_deletes_several_images_and_their_objects_at_once(): void
    {
        Storage::fake('s3');

        $ids = [];

        foreach (['one', 'two', 'three'] as $n) {
            Storage::disk('s3')->put("2026/07/{$n}.png", 'x');
            $ids[] = $this->record(['key' => "2026/07/{$n}.png", 'name' => "{$n}.png"])->id;
        }

        $this->deleteJson('/cms/media', ['ids' => $ids])
            ->assertOk()
            ->assertJsonPath('kept', [])
            ->assertJsonCount(3, 'deleted');

        $this->assertDatabaseCount('media', 0);
        Storage::disk('s3')->assertMissing('2026/07/one.png');
        Storage::disk('s3')->assertMissing('2026/07/three.png');
    }

    public function test_it_keeps_an_image_a_published_page_still_uses(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('2026/07/abc123.png', 'x');

        $media = $this->record();
        $this->pageUsing($media->url(), 'published', 'Home');

        $this->deleteJson('/cms/media', ['ids' => [$media->id]])
            ->assertOk()
            ->assertJsonPath('deleted', [])
            ->assertJsonPath('kept.0.usedBy.0', 'Home (live)');

        $this->assertDatabaseCount('media', 1);
        Storage::disk('s3')->assertExists('2026/07/abc123.png');
    }

    public function test_it_keeps_an_image_a_draft_still_uses(): void
    {
        Storage::fake('s3');

        $media = $this->record();
        $this->pageUsing($media->url(), 'draft', 'Contact');

        $this->deleteJson('/cms/media', ['ids' => [$media->id]])
            ->assertOk()
            ->assertJsonPath('kept.0.usedBy.0', 'Contact (draft)');
    }

    public function test_it_keeps_an_image_the_site_chrome_still_uses(): void
    {
        Storage::fake('s3');

        $media = $this->record();

        DB::table('settings')->updateOrInsert(
            ['key' => 'globals'],
            ['value' => json_encode(['header' => ['logo' => $media->url()]]), 'updated_at' => now()],
        );

        $this->deleteJson('/cms/media', ['ids' => [$media->id]])
            ->assertOk()
            ->assertJsonPath('kept.0.usedBy.0', 'Site header and footer');
    }

    public function test_it_keeps_an_image_a_saved_section_still_uses(): void
    {
        Storage::fake('s3');

        $media = $this->record();

        DB::table('reusable_sections')->insert([
            'name' => 'Hero with photo',
            'type' => 'hero',
            'block' => json_encode(['data' => ['image' => ['src' => $media->url()]]]),
            'created_by' => 'tester',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson('/cms/media', ['ids' => [$media->id]])
            ->assertOk()
            ->assertJsonPath('kept.0.usedBy.0', 'Saved section: Hero with photo');
    }

    public function test_publish_history_alone_does_not_stop_a_delete(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('2026/07/abc123.png', 'x');

        $media = $this->record();
        $page = $this->pageUsing('/media/2026/07/unrelated.png', 'published', 'Home');

        DB::table('page_revisions')->insert([
            'page_id' => $page->id,
            'n' => 4,
            'action' => 'publish',
            'by' => 'tester',
            'sections' => json_encode([['data' => ['image' => ['src' => $media->url()]]]]),
            'section_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/cms/media/usage', ['ids' => [$media->id]])
            ->assertOk()
            ->assertJsonPath('items.0.usedBy', [])
            ->assertJsonPath('items.0.history.0', 'Home version 4');

        $this->deleteJson('/cms/media', ['ids' => [$media->id]])
            ->assertOk()
            ->assertJsonCount(1, 'deleted');

        $this->assertDatabaseCount('media', 0);
    }

    public function test_a_mixed_delete_removes_the_free_ones_and_keeps_the_used(): void
    {
        Storage::fake('s3');

        $free = $this->record(['key' => '2026/07/free.png', 'name' => 'free.png']);
        $used = $this->record(['key' => '2026/07/used.png', 'name' => 'used.png']);

        $this->pageUsing($used->url(), 'published', 'Home');

        $this->deleteJson('/cms/media', ['ids' => [$free->id, $used->id]])
            ->assertOk()
            ->assertJsonPath('deleted', ['free.png'])
            ->assertJsonPath('kept.0.name', 'used.png');

        $this->assertDatabaseHas('media', ['name' => 'used.png']);
        $this->assertDatabaseMissing('media', ['name' => 'free.png']);
    }

    public function test_deleting_one_image_also_refuses_when_it_is_in_use(): void
    {
        Storage::fake('s3');

        $media = $this->record();
        $this->pageUsing($media->url(), 'published', 'Home');

        $this->deleteJson("/cms/media/{$media->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Home (live)'));

        $this->assertDatabaseCount('media', 1);
    }

    public function test_it_ignores_ids_that_do_not_exist(): void
    {
        Storage::fake('s3');

        $this->deleteJson('/cms/media', ['ids' => [999, 'abc', null]])
            ->assertOk()
            ->assertJsonPath('deleted', [])
            ->assertJsonPath('kept', []);
    }

    public function test_the_library_lists_what_has_been_uploaded(): void
    {
        Storage::fake('s3');

        $this->record(['key' => '2026/07/one.png', 'name' => 'one.png']);
        $this->record(['key' => '2026/07/two.pdf', 'name' => 'two.pdf', 'mime' => 'application/pdf', 'width' => null, 'height' => null]);

        $this->get('/cms/media')->assertInertia(function ($page) {
            $items = $page->toArray()['props']['items'];

            $this->assertCount(2, $items);
            $this->assertSame(['two.pdf', 'one.png'], array_column($items, 'name'));
            $this->assertFalse($items[0]['isImage']);
            $this->assertTrue($items[1]['isImage']);
            $this->assertSame('PNG · 800 × 600', $items[1]['meta']);
        });
    }

    public function test_an_image_can_be_opened_straight_from_a_link(): void
    {
        Storage::fake('s3');

        $medium = $this->record(['key' => '2026/07/one.png', 'name' => 'one.png']);

        $this->get('/cms/media')->assertInertia(fn ($page) => $page->where('selected', null));

        /* The whole image, not its id: the library is paged, and an id resolved against the rows on
           screen would open nothing whenever the picture sits on another page. */
        $this->get("/cms/media?selected={$medium->id}")->assertInertia(function ($page) use ($medium) {
            $selected = $page->toArray()['props']['selected'];

            $this->assertSame($medium->id, $selected['id']);
            $this->assertSame('one.png', $selected['name']);
            $this->assertSame('/media/2026/07/one.png', $selected['url']);
        });
    }

    /* Deleted between somebody searching and clicking, so it selects nothing rather than erroring. */
    public function test_a_link_to_an_image_that_is_gone_still_opens_the_screen(): void
    {
        Storage::fake('s3');

        $this->get('/cms/media?selected=98765')->assertOk()
            ->assertInertia(fn ($page) => $page->where('selected', null));
        $this->get('/cms/media?selected=nonsense')->assertOk()
            ->assertInertia(fn ($page) => $page->where('selected', null));
    }

    public function test_the_library_pages_and_searches_on_the_server(): void
    {
        Storage::fake('s3');

        foreach (range(1, 30) as $i) {
            $this->record(['key' => sprintf('2026/07/p%02d.png', $i), 'name' => sprintf('photo-%02d.png', $i)]);
        }

        $this->get('/cms/media')->assertOk()->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            $this->assertCount(25, $props['items']);
            $this->assertSame(30, $props['pagination']['total']);
            $this->assertSame(2, $props['pagination']['lastPage']);
        });

        $this->get('/cms/media?page=2')->assertOk()->assertInertia(fn ($page) => $this->assertCount(
            5, $page->toArray()['props']['items'],
        ));

        /* The oldest image is on page two, so a search that only looked at page one would miss it. */
        $this->get('/cms/media?q=photo-01')->assertOk()->assertInertia(fn ($page) => $this->assertSame(
            ['photo-01.png'], array_column($page->toArray()['props']['items'], 'name'),
        ));
    }

    public function test_an_image_on_another_page_still_opens(): void
    {
        Storage::fake('s3');

        $first = $this->record(['key' => '2026/07/first.png', 'name' => 'first.png']);

        foreach (range(1, 30) as $i) {
            $this->record(['key' => sprintf('2026/07/n%02d.png', $i), 'name' => sprintf('newer-%02d.png', $i)]);
        }

        $this->get("/cms/media?selected={$first->id}")->assertOk()->assertInertia(function ($page) use ($first) {
            $props = $page->toArray()['props'];

            $this->assertNotContains('first.png', array_column($props['items'], 'name'));
            $this->assertSame($first->id, $props['selected']['id']);
        });
    }
}
