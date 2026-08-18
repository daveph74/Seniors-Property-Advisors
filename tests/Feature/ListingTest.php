<?php

namespace Tests\Feature;

use App\Cms\Listing;
use App\Models\Enquiry;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * The paging every admin list goes through. Its two jobs are refusing a page size nobody offered
 * and never leaving somebody on a page that is not there.
 */
class ListingTest extends TestCase
{
    private function request(array $query = []): Request
    {
        return Request::create('/cms/enquiries', 'GET', $query);
    }

    private function make(int $n): void
    {
        foreach (range(1, $n) as $i) {
            Enquiry::create([
                'name' => sprintf('Sender %03d', $i), 'email' => "s{$i}@example.com", 'consented' => true,
            ]);
        }
    }

    private function slice(array $query): array
    {
        return Listing::slice(Enquiry::query()->orderBy('id'), $this->request($query));
    }

    public function test_the_page_size_comes_from_an_allowlist(): void
    {
        $this->assertSame(25, Listing::perPage($this->request()));
        $this->assertSame(50, Listing::perPage($this->request(['per_page' => 50])));
        $this->assertSame(100, Listing::perPage($this->request(['per_page' => '100'])));
    }

    /* Otherwise the size selector is a way to ask the server for the whole table. */
    public function test_a_size_nobody_offered_falls_back_to_the_default(): void
    {
        foreach ([1000000, 0, -5, 26, 'all'] as $asked) {
            $this->assertSame(25, Listing::perPage($this->request(['per_page' => $asked])), (string) $asked);
        }
    }

    public function test_it_slices_the_page_asked_for(): void
    {
        $this->make(60);

        ['rows' => $rows, 'meta' => $meta] = $this->slice(['page' => 2]);

        $this->assertCount(25, $rows);
        $this->assertSame('Sender 026', $rows->first()->name);
        $this->assertSame(['page' => 2, 'from' => 26, 'to' => 50, 'total' => 60, 'lastPage' => 3], [
            'page' => $meta['page'], 'from' => $meta['from'], 'to' => $meta['to'],
            'total' => $meta['total'], 'lastPage' => $meta['lastPage'],
        ]);
    }

    /* Deleting the last row on the last page would otherwise strand somebody on an empty one. */
    public function test_a_page_past_the_end_is_clamped_to_the_last(): void
    {
        $this->make(30);

        $this->assertSame(2, $this->slice(['page' => 99])['meta']['page']);
        $this->assertCount(5, $this->slice(['page' => 99])['rows']);
    }

    public function test_a_page_below_one_is_clamped_up(): void
    {
        $this->make(30);

        foreach ([0, -3, 'nonsense'] as $asked) {
            $this->assertSame(1, $this->slice(['page' => $asked])['meta']['page'], (string) $asked);
        }
    }

    public function test_the_boundaries_either_side_of_one_full_page(): void
    {
        $this->make(25);
        $this->assertSame(1, $this->slice([])['meta']['lastPage']);
        $this->assertSame(25, $this->slice([])['meta']['to']);

        $this->make(1);
        $this->assertSame(2, $this->slice([])['meta']['lastPage']);
    }

    public function test_an_empty_list_counts_from_zero(): void
    {
        $meta = $this->slice([])['meta'];

        $this->assertSame(['page' => 1, 'from' => 0, 'to' => 0, 'total' => 0, 'lastPage' => 1], [
            'page' => $meta['page'], 'from' => $meta['from'], 'to' => $meta['to'],
            'total' => $meta['total'], 'lastPage' => $meta['lastPage'],
        ]);
    }

    public function test_it_reports_the_sizes_the_selector_may_offer(): void
    {
        $this->assertSame(Listing::SIZES, $this->slice([])['meta']['sizes']);
    }
}
