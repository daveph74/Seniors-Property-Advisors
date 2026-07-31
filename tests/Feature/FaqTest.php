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

    public function test_categories_can_be_created_and_renamed(): void
    {
        $this->post('/cms/faq-categories', ['name' => 'Fees'])->assertRedirect();

        $category = FaqCategory::firstOrFail();

        $this->assertSame('Fees', $category->name);

        $this->patch("/cms/faq-categories/{$category->id}", ['name' => 'Fees and costs'])->assertRedirect();

        $this->assertSame('Fees and costs', $category->refresh()->name);
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
            $this->assertSame(1, $props['categories'][0]['count']);
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
