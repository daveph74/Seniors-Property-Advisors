<?php

namespace Tests\Feature;

use App\Content\ContentLibrary;
use App\Models\Faq;
use App\Models\FaqCategory;
use Tests\TestCase;

class FaqTest extends TestCase
{
    private function category(string $name = 'Downsizing'): FaqCategory
    {
        return FaqCategory::create(['name' => $name, 'sort_order' => 1]);
    }

    private function faq(array $overrides = []): Faq
    {
        return Faq::create(array_merge([
            'question' => 'What does an advisor cost?',
            'answer' => 'Nothing upfront.',
            'sort_order' => 1,
            'active' => true,
        ], $overrides));
    }

    public function test_it_creates_a_question_and_puts_it_last(): void
    {
        $this->faq(['question' => 'First', 'sort_order' => 1]);

        $this->post('/cms/faqs', [
            'question' => 'Second',
            'answer' => 'An answer.',
        ])->assertRedirect();

        $created = Faq::where('question', 'Second')->firstOrFail();

        $this->assertSame(2, $created->sort_order);
        $this->assertTrue($created->active);
        $this->assertNotNull($created->last_updated_by);
    }

    public function test_it_requires_a_question_and_an_answer(): void
    {
        $this->post('/cms/faqs', ['question' => '', 'answer' => ''])
            ->assertSessionHasErrors(['question', 'answer']);

        $this->assertSame(0, Faq::count());
    }

    public function test_it_strips_markup_from_what_is_saved(): void
    {
        $this->post('/cms/faqs', [
            'question' => 'Is it <script>alert(1)</script>safe?',
            'answer' => '<b>Yes</b> it is.',
        ])->assertRedirect();

        $faq = Faq::firstOrFail();

        $this->assertStringNotContainsString('<', $faq->question);
        $this->assertStringNotContainsString('<', $faq->answer);
        $this->assertSame('Yes it is.', $faq->answer);
    }

    public function test_it_updates_and_hides_a_question(): void
    {
        $faq = $this->faq();

        $this->patch("/cms/faqs/{$faq->id}", [
            'question' => 'What does it cost?',
            'answer' => 'Nothing upfront.',
            'active' => false,
        ])->assertRedirect();

        $faq->refresh();

        $this->assertSame('What does it cost?', $faq->question);
        $this->assertFalse($faq->active);
    }

    public function test_it_deletes_a_question(): void
    {
        $faq = $this->faq();

        $this->delete("/cms/faqs/{$faq->id}")->assertRedirect();

        $this->assertSame(0, Faq::count());
    }

    public function test_it_reorders_questions(): void
    {
        $a = $this->faq(['question' => 'A', 'sort_order' => 1]);
        $b = $this->faq(['question' => 'B', 'sort_order' => 2]);

        $this->post('/cms/faqs/reorder', ['ids' => [$b->id, $a->id]])->assertRedirect();

        $this->assertSame(1, $b->refresh()->sort_order);
        $this->assertSame(2, $a->refresh()->sort_order);
    }

    public function test_reordering_a_filtered_list_leaves_the_hidden_questions_where_they_were(): void
    {
        $first = $this->faq(['question' => 'First', 'sort_order' => 1]);
        $hidden = $this->faq(['question' => 'Filtered out', 'sort_order' => 2]);
        $last = $this->faq(['question' => 'Last', 'sort_order' => 3]);

        /* What the screen sends while a search or a category filter is on. */
        $this->post('/cms/faqs/reorder', ['ids' => [$last->id, $first->id]])->assertRedirect();

        $this->assertSame(1, $last->refresh()->sort_order);
        $this->assertSame(3, $first->refresh()->sort_order);
        $this->assertSame(2, $hidden->refresh()->sort_order);
    }

    public function test_categories_can_be_created_and_renamed(): void
    {
        $this->post('/cms/faq-categories', ['name' => 'Fees for families'])->assertRedirect();

        $category = FaqCategory::where('name', 'Fees for families')->sole();

        $this->patch("/cms/faq-categories/{$category->id}", ['name' => 'Fees and costs'])->assertRedirect();

        $this->assertSame('Fees and costs', $category->refresh()->name);
    }

    public function test_the_seeded_categories_are_exactly_the_scope_list_in_its_order(): void
    {
        $this->assertSame([
            'General',
            'Selling',
            'Downsizing',
            'Fees',
            'The advisory process',
            'Property agents',
            'Legal and financial considerations',
        ], FaqCategory::orderBy('sort_order')->orderBy('id')->pluck('name')->all());
    }

    public function test_the_scope_categories_are_never_duplicated(): void
    {
        $before = FaqCategory::count();

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        $this->assertSame($before, FaqCategory::count());
    }

    public function test_the_builder_can_pick_a_category_that_has_no_questions_yet(): void
    {
        $empty = $this->category('Nothing filed here');

        $choices = (new ContentLibrary)->choices();

        /* Wider than the reader-facing list, which hides a grouping with nothing behind it. */
        $this->assertContains($empty->name, $choices['allFaqCategories']);
        $this->assertNotContains($empty->name, (new ContentLibrary)->for(null)['faqCategories']);

        $empty->update(['active' => false]);

        $this->assertNotContains($empty->name, (new ContentLibrary)->choices()['allFaqCategories']);
    }

    public function test_categories_can_be_reordered(): void
    {
        $first = $this->category('Fees at the top');
        $second = $this->category('Second');

        $this->post('/cms/faq-categories/reorder', ['ids' => [$second->id, $first->id]])
            ->assertRedirect();

        $this->assertTrue($second->refresh()->sort_order < $first->refresh()->sort_order);
    }

    public function test_a_category_can_be_hidden_without_touching_its_questions(): void
    {
        /* Its own name: "Downsizing" is one of the seeded seven, and category names are unique
           now, so reusing the default would fail validation rather than hide anything. */
        $category = $this->category('Moving house');
        $faq = $this->faq(['faq_category_id' => $category->id]);

        $this->patch("/cms/faq-categories/{$category->id}", ['name' => $category->name, 'active' => false])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertFalse($category->refresh()->active);
        $this->assertTrue($faq->refresh()->active);

        $library = (new ContentLibrary)->for(null);

        /* Off the filters, but the question still answers. */
        $this->assertNotContains($category->name, $library['faqCategories']);
        $this->assertContains($faq->question, array_column($library['faqs'], 'question'));
    }

    public function test_deleting_a_category_keeps_its_questions(): void
    {
        $category = $this->category();
        $faq = $this->faq(['faq_category_id' => $category->id]);

        $this->delete("/cms/faq-categories/{$category->id}")->assertRedirect();

        $this->assertDatabaseMissing('faq_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('faqs', ['id' => $faq->id]);
        $this->assertNull($faq->refresh()->faq_category_id);
    }

    public function test_only_a_super_administrator_can_delete_a_category(): void
    {
        $category = $this->category();

        $this->actingAs($this->clientAdmin());

        $this->delete("/cms/faq-categories/{$category->id}")->assertForbidden();

        $this->assertDatabaseHas('faq_categories', ['id' => $category->id]);
    }

    public function test_the_filters_only_offer_groups_that_have_a_question_to_show(): void
    {
        $withQuestions = $this->category('Downsizing');
        $empty = $this->category('Nothing here yet');

        $this->faq(['faq_category_id' => $withQuestions->id]);
        $this->faq(['question' => 'Hidden one', 'faq_category_id' => $empty->id, 'active' => false]);

        $offered = (new ContentLibrary)->for(null)['faqCategories'];

        $this->assertContains('Downsizing', $offered);
        $this->assertNotContains('Nothing here yet', $offered);
        $this->assertNotContains('General', $offered);
    }

    public function test_a_group_offered_on_a_page_respects_the_page_assignment(): void
    {
        $category = $this->category('Contact only');

        $this->faq(['faq_category_id' => $category->id, 'page_slug' => 'contact']);

        $this->assertContains('Contact only', (new ContentLibrary)->for('contact')['faqCategories']);
        $this->assertNotContains('Contact only', (new ContentLibrary)->for('about-us')['faqCategories']);
    }

    public function test_the_library_only_offers_active_questions_in_order(): void
    {
        $this->faq(['question' => 'Second', 'sort_order' => 2]);
        $this->faq(['question' => 'First', 'sort_order' => 1]);
        $this->faq(['question' => 'Hidden', 'sort_order' => 3, 'active' => false]);

        $faqs = (new ContentLibrary)->for('home')['faqs'];

        $this->assertSame(['First', 'Second'], array_column($faqs, 'question'));
    }

    public function test_a_question_assigned_to_a_page_is_only_offered_to_that_page(): void
    {
        $this->faq(['question' => 'Everywhere']);
        $this->faq(['question' => 'Contact only', 'page_slug' => 'contact', 'sort_order' => 2]);

        $library = new ContentLibrary;

        $this->assertSame(['Everywhere'], array_column($library->for('home')['faqs'], 'question'));
        $this->assertSame(
            ['Everywhere', 'Contact only'],
            array_column($library->for('contact')['faqs'], 'question'),
        );
    }

    public function test_the_library_reports_the_category_name(): void
    {
        $this->faq(['faq_category_id' => $this->category('Fees')->id]);

        $this->assertSame('Fees', (new ContentLibrary)->for('home')['faqs'][0]['category']);
    }

    public function test_the_admin_screen_lists_real_questions_and_categories(): void
    {
        $category = $this->category('Selling');
        $this->faq(['faq_category_id' => $category->id]);

        $this->get('/cms/faqs')->assertOk()->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            $this->assertCount(1, $props['faqs']);
            $this->assertSame('Selling', $props['faqs'][0]['category']);
            $selling = collect($props['categories'])->firstWhere('name', 'Selling');

            $this->assertSame(1, $selling['count']);
        });
    }

    public function test_the_public_page_receives_the_library(): void
    {
        $this->faq(['question' => 'Shown publicly']);

        $this->get('/')->assertOk()->assertInertia(function ($page) {
            $faqs = $page->toArray()['props']['library']['faqs'];

            $this->assertSame(['Shown publicly'], array_column($faqs, 'question'));
        });
    }
}
