<?php

namespace Database\Seeders;

use App\Content\Html;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Database\Seeder;

/**
 * Sample articles and questions, so the blog and FAQ listings have something to list on a
 * development machine. Client copy replaces all of it.
 *
 * Called from DatabaseSeeder and deliberately **not** from Tests\TestCase, which seeds
 * ContentSeeder alone. Content added to the seed every test runs would break every count
 * those tests assert; UserSeeder sits behind the same seam for the same reason.
 */
class SampleContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->faqs();
        $this->articles();
    }

    private function faqs(): void
    {
        $questions = [
            ['General', 'What does Agent Finder actually do?', 'We research the agents working in your suburb, shortlist the ones who genuinely suit your property, and sit with you while you compare them. We are not an agency and we never list your home ourselves.'],
            ['General', 'Is there any cost to me?', 'No. Our advice to you is free. We are paid a referral fee by the agent you choose, at no additional cost to your sale.'],
            ['Selling', 'Should we sell before we buy?', 'It depends on your finances and your appetite for uncertainty. Your advisor will walk through both orders with you and what each would mean for your timeline.'],
            ['Selling', 'Do we need to renovate before selling?', 'Usually far less than people expect. We will tell you honestly which work pays for itself in your market and which does not.'],
            ['Downsizing', 'When is the right time to start planning?', 'Earlier than most people think — six to twelve months before you would like to move gives you room to make decisions calmly rather than under pressure.'],
            ['Downsizing', 'Can you help if we are moving interstate?', 'Yes. We work with agents across the country and can coordinate a sale here while you settle somewhere else.'],
            ['Fees', 'What is a reasonable commission?', 'It varies by suburb and price bracket. You will see the real range for your area, so you can tell a fair quote from an optimistic one.'],
            ['The advisory process', 'How long does the whole process take?', 'From first conversation to choosing an agent is usually two to three weeks. The sale itself follows your timeline, not ours.'],
            ['Property agents', 'How do you choose which agents to recommend?', 'Recent sales in your streets, listing accuracy, seller reviews and how they handle a vendor who needs time. Never who pays us most.'],
            ['Legal and financial considerations', 'Will selling affect my age pension?', 'It can, depending on what you do with the proceeds. We are not financial advisors, so we will tell you when a question needs one and help you prepare for that conversation.'],
        ];

        foreach ($questions as $order => [$category, $question, $answer]) {
            $id = FaqCategory::where('name', $category)->value('id');

            Faq::updateOrCreate(
                ['question' => $question],
                [
                    'faq_category_id' => $id,
                    'answer' => $answer,
                    'sort_order' => $order + 1,
                    'active' => true,
                    'last_updated_by' => 'Sample data',
                ],
            );
        }
    }

    private function articles(): void
    {
        /* Three categories rather than one so the listing's filter row has something to do —
           with a single category it renders nothing, and the chips go unexercised. */
        $categories = collect(['Property Advice' => 'property-advice', 'Downsizing' => 'downsizing', 'Selling' => 'selling'])
            ->mapWithKeys(fn ($slug, $name) => [$slug => BlogCategory::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'active' => true],
            )]);

        $articles = [
            [
                'downsizing',
                'when-to-start-planning-a-downsize',
                'When to start planning a downsize',
                'Most people leave it later than they need to. Here is what a comfortable timeline actually looks like.',
                'Helen Marsh',
                18,
                '<p>The question we are asked most often is when to begin. The honest answer is that there is no deadline — but there is a difference between deciding on your own terms and deciding because something forced the issue.</p><h2>Six to twelve months is comfortable</h2><p>That is enough time to see your options without hurrying. It leaves room to prepare the house properly, to watch how similar homes sell in your area, and to change your mind once or twice along the way.</p><p>It also means you are choosing an agent while you still have leverage, rather than accepting whoever is available.</p><h2>What to do first</h2><ul><li>Write down what the next home needs to have, and what it does not</li><li>Get a sense of what your home is worth, without committing to anything</li><li>Talk to whoever else the decision affects</li></ul><p>None of that requires a real estate agent, and none of it commits you to selling.</p>',
            ],
            [
                'selling',
                'what-the-age-pension-means-when-you-sell',
                'What the age pension means when you sell',
                'Selling the family home can change your pension. What changes, and what does not.',
                'Helen Marsh',
                34,
                '<p>Your home is exempt from the age pension assets test. The money you receive for it is not, and that catches people out.</p><h2>The exemption has a time limit</h2><p>If you sell intending to buy another home, the proceeds are generally exempt for a period while you do — but the rules are specific and they change. Check them against your own circumstances rather than against a friend\'s.</p><blockquote>We are property advisors, not financial advisors. On anything touching your pension, we will tell you to get proper advice — and help you arrive at that appointment with the right questions.</blockquote><p>What we can do is make sure the sale itself is not the part that costs you.</p>',
            ],
            [
                'property-advice',
                'five-questions-to-ask-before-signing-with-an-agent',
                'Five questions to ask before signing with an agent',
                'The answers tell you more than any appraisal will.',
                'Daniel Ruiz',
                52,
                '<p>An appraisal is an opinion, and an optimistic one wins listings. These five questions are harder to answer well.</p><ol><li><strong>What have you sold in my street in the last year?</strong> Not the suburb — the street, or the ones either side of it.</li><li><strong>What did those homes first list at, and what did they sell for?</strong> The gap tells you how the appraisal was arrived at.</li><li><strong>What is your commission, and what is included?</strong> Marketing is often quoted separately.</li><li><strong>Who will actually run my campaign?</strong> The person in your living room is not always the person doing the work.</li><li><strong>What would make you advise me not to sell right now?</strong> An agent who cannot answer this is selling to you, not for you.</li></ol><p>Take notes. Compare them side by side afterwards, when nobody is in the room.</p>',
            ],
        ];

        foreach ($articles as [$categorySlug, $slug, $title, $summary, $author, $daysAgo, $body]) {
            $post = BlogPost::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'summary' => $summary,
                    /* Purified here because a seeder skips the request that normally does it, and
                       Pages/Article.jsx prints the stored body directly. */
                    'body' => Html::clean($body),
                    'author_name' => $author,
                    'status' => 'published',
                    'published_at' => now()->subDays($daysAgo),
                    'last_updated_by' => 'Sample data',
                ],
            );

            $post->categories()->sync([$categories[$categorySlug]->id]);
        }
    }
}
