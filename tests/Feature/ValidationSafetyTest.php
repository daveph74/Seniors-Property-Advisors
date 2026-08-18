<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Testimonial;
use Tests\TestCase;

/**
 * Scope §14. These cover the holes an audit of it turned up, rather than the parts that already
 * held — the point of each is a request that used to be accepted, crash, or pass silently.
 */
class ValidationSafetyTest extends TestCase
{
    public function test_markup_alone_is_not_a_name(): void
    {
        /* It passed `required`, then sanitising emptied it, and a NOT NULL column turned an
           editor's typo into a 500. Sanitising happens before the rules now. */
        $this->post('/cms/testimonials', ['name' => '<hr>', 'quote' => 'Kind people.'])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Testimonial::count());
    }

    public function test_markup_alone_is_not_a_question(): void
    {
        $this->post('/cms/faqs', ['question' => '<b></b>', 'answer' => 'Something.'])
            ->assertSessionHasErrors('question');

        $this->assertSame(0, Faq::count());
    }

    public function test_a_faq_category_needs_a_name(): void
    {
        $before = FaqCategory::count();

        $this->post('/cms/faq-categories', ['name' => ''])->assertSessionHasErrors('name');
        $this->post('/cms/faq-categories', ['name' => '   '])->assertSessionHasErrors('name');
        $this->post('/cms/faq-categories', ['name' => str_repeat('a', 121)])
            ->assertSessionHasErrors('name');

        $this->assertSame($before, FaqCategory::count());
    }

    public function test_two_faq_categories_cannot_share_a_name(): void
    {
        $this->post('/cms/faq-categories', ['name' => 'Moving house'])->assertSessionHasNoErrors();

        $this->post('/cms/faq-categories', ['name' => 'Moving house'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, FaqCategory::where('name', 'Moving house')->count());
    }

    public function test_a_faq_category_can_be_saved_under_its_own_name(): void
    {
        $this->post('/cms/faq-categories', ['name' => 'Moving house'])->assertSessionHasNoErrors();

        $category = FaqCategory::where('name', 'Moving house')->sole();

        /* Its own name is not a duplicate of itself — the guard has to let a toggle through. */
        $this->patch("/cms/faq-categories/{$category->id}", ['name' => 'Moving house', 'active' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse($category->refresh()->active);
    }

    public function test_two_blog_categories_cannot_share_a_name(): void
    {
        $this->post('/cms/blog-categories', ['name' => 'Property Advice'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, BlogCategory::where('name', 'Property Advice')->count());
    }

    public function test_renaming_a_blog_category_moves_its_web_address_too(): void
    {
        $category = BlogCategory::where('slug', 'property-advice')->sole();

        $this->patch("/cms/blog-categories/{$category->id}", ['name' => 'Aged care'])
            ->assertSessionHasNoErrors();

        /* The slug is what a reader's filtered link carries. Leaving it behind meant a category
           called Aged care still answered to ?category=property-advice. */
        $this->assertSame('aged-care', $category->refresh()->slug);
    }

    public function test_a_rejected_save_says_which_field_was_wrong(): void
    {
        /* The screens read these keys to put a message under the offending field. A validation
           error with no field name can only ever become a toast. */
        $this->post('/cms/faqs', ['question' => '', 'answer' => ''])
            ->assertSessionHasErrors(['question', 'answer']);

        $this->post('/cms/testimonials', ['name' => '', 'quote' => ''])
            ->assertSessionHasErrors(['name', 'quote']);
    }

    public function test_uncategorised_keeps_its_address_when_renamed(): void
    {
        $fallback = BlogCategory::fallback();

        $this->patch("/cms/blog-categories/{$fallback->id}", ['name' => 'General'])
            ->assertSessionHasNoErrors();

        /* Everything looks that one up by slug, so it is the one that must not move. */
        $this->assertSame(BlogCategory::UNCATEGORISED, $fallback->refresh()->slug);
    }
}
