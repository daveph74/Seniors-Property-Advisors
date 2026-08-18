<?php

namespace Tests\Feature;

use App\Content\PageContentStore;
use App\Models\Page;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PageDetailsTest extends TestCase
{
    public function test_a_page_can_be_renamed(): void
    {
        $this->patch('/cms/pages/1/details', ['title' => 'Home page'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Home page', Page::where('slug', 'home')->value('title'));
    }

    public function test_renaming_updates_the_public_page_immediately(): void
    {
        config(['app.debug' => false]);

        $this->get('/')->assertOk();

        $this->patch('/cms/pages/1/details', ['title' => 'Renamed live'])->assertRedirect();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('title', 'Renamed live'));
    }

    public function test_the_seo_title_and_description_can_be_set(): void
    {
        $this->patch('/cms/pages/1/details', [
            'title' => 'Home',
            'seo' => ['title' => 'Find a trusted agent', 'description' => 'Compare local agents.'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $seo = (new PageContentStore)->document('home')['seo'];

        $this->assertSame('Find a trusted agent', $seo['title']);
        $this->assertSame('Compare local agents.', $seo['description']);
    }

    public function test_setting_one_seo_field_keeps_the_others(): void
    {
        $this->patch('/cms/pages/1/details', [
            'title' => 'Home',
            'seo' => ['title' => 'Tab title', 'description' => 'Kept'],
        ])->assertRedirect();

        $this->patch('/cms/pages/1/details', [
            'title' => 'Home',
            'seo' => ['title' => 'Changed'],
        ])->assertRedirect();

        $seo = (new PageContentStore)->document('home')['seo'];

        $this->assertSame('Changed', $seo['title']);
        $this->assertSame('Kept', $seo['description']);
    }

    public function test_clearing_an_seo_field_removes_it(): void
    {
        $this->patch('/cms/pages/1/details', [
            'title' => 'Home',
            'seo' => ['title' => 'Tab title'],
        ])->assertRedirect();

        $this->patch('/cms/pages/1/details', [
            'title' => 'Home',
            'seo' => ['title' => null],
        ])->assertRedirect();

        $this->assertArrayNotHasKey('title', (new PageContentStore)->document('home')['seo']);
    }

    public function test_renaming_does_not_touch_the_section_tree(): void
    {
        $before = (new PageContentStore)->document('home')['published'];

        $this->patch('/cms/pages/1/details', ['title' => 'Renamed'])->assertRedirect();

        $this->assertSame($before, (new PageContentStore)->document('home')['published']);
    }

    public function test_a_page_cannot_be_left_nameless(): void
    {
        $this->patch('/cms/pages/1/details', ['title' => '  '])->assertSessionHasErrors('title');
        $this->patch('/cms/pages/1/details', ['title' => ''])->assertSessionHasErrors('title');

        $this->assertNotSame('', Page::where('slug', 'home')->value('title'));
    }

    public function test_markup_is_stripped_from_the_name(): void
    {
        $this->patch('/cms/pages/1/details', ['title' => '<script>x</script>Clean name'])->assertRedirect();

        $this->assertSame('xClean name', Page::where('slug', 'home')->value('title'));
    }

    public function test_the_builder_receives_the_page_details(): void
    {
        $this->patch('/cms/pages/1/details', [
            'title' => 'Editable',
            'seo' => ['title' => 'Tab', 'description' => 'Desc'],
        ])->assertRedirect();

        $this->get('/cms/pages/1/edit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('page.title', 'Editable')
                ->where('page.seo.title', 'Tab')
                ->where('page.url', '/'));
    }

    public function test_the_pages_list_shows_the_new_name(): void
    {
        $this->patch('/cms/pages/1/details', ['title' => 'Brand new name'])->assertRedirect();

        $this->get('/cms/pages')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('pages.0.title', 'Brand new name'));
    }

    public function test_an_unknown_page_cannot_be_renamed(): void
    {
        $this->patch('/cms/pages/99/details', ['title' => 'Nope'])->assertNotFound();
    }
}
