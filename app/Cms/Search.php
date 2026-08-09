<?php

namespace App\Cms;

use App\Models\BlogPost;
use App\Models\Enquiry;
use App\Models\Faq;
use App\Models\Media;
use App\Models\Page;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the header's search box reaches. The placeholder has always promised pages, articles and
 * media; this answers for those three and for the other content an editor can actually act on.
 *
 * Every group is capped and every match is a link. Nothing here is a listing screen — the point
 * is to get somebody to the one record they had in mind, so a wide match is worse than no match.
 *
 * Bodies are searched but never returned. An article body is HTML and a testimonial quote can run
 * long; both would push the useful part of a result off the end of a row.
 */
class Search
{
    public const PER_GROUP = 5;

    private const MIN_LENGTH = 2;

    public function for(string $term): array
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return [];
        }

        $groups = [
            'Pages' => $this->pages($term),
            'Articles' => $this->articles($term),
            'FAQs' => $this->faqs($term),
            'Testimonials' => $this->testimonials($term),
            'Media' => $this->media($term),
            'Enquiries' => $this->enquiries($term),
        ];

        return array_values(array_filter(
            array_map(
                fn (array $results, string $label) => ['label' => $label, 'results' => $results],
                $groups,
                array_keys($groups),
            ),
            fn (array $group) => $group['results'] !== [],
        ));
    }

    private function pages(string $term): array
    {
        return Page::query()
            ->where(fn (Builder $q) => $this->match($q, $term, ['title', 'nav_label', 'slug']))
            ->orderBy('title')
            ->limit(self::PER_GROUP)
            ->get()
            /* The builder is keyed on `cms_id`, not the primary key — `CmsPageController::edit`
               resolves through `findByCmsId`, so a link built from `id` 404s. */
            ->map(fn (Page $page) => [
                'id' => $page->cms_id,
                'title' => $page->title,
                'meta' => $page->url,
                'href' => "/cms/pages/{$page->cms_id}/edit",
            ])
            ->all();
    }

    private function articles(string $term): array
    {
        return BlogPost::query()
            ->where(fn (Builder $q) => $this->match($q, $term, ['title', 'summary', 'slug', 'body']))
            ->orderByDesc('published_at')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (BlogPost $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'meta' => $post->status,
                'href' => "/cms/blog/{$post->id}/edit",
            ])
            ->all();
    }

    private function faqs(string $term): array
    {
        return Faq::query()
            ->where(fn (Builder $q) => $this->match($q, $term, ['question', 'answer']))
            ->orderBy('sort_order')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Faq $faq) => [
                'id' => $faq->id,
                'title' => $faq->question,
                'meta' => $faq->active ? null : 'Hidden',
                'href' => '/cms/faqs',
            ])
            ->all();
    }

    private function testimonials(string $term): array
    {
        return Testimonial::query()
            ->where(fn (Builder $q) => $this->match($q, $term, ['name', 'quote', 'location', 'headline']))
            ->orderBy('sort_order')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Testimonial $testimonial) => [
                'id' => $testimonial->id,
                'title' => $testimonial->name,
                'meta' => $testimonial->location,
                'href' => '/cms/testimonials',
            ])
            ->all();
    }

    private function media(string $term): array
    {
        return Media::query()
            ->where(fn (Builder $q) => $this->match($q, $term, ['name', 'alt', 'caption', 'key']))
            ->orderByDesc('id')
            ->limit(self::PER_GROUP)
            ->get()
            /* The alt text is searched but not shown. It is a description written for somebody who
               cannot see the picture, so as a row it read as a sentence with no sign it described
               an image at all — "Client portrait — Rachel" tells you nothing about which file that
               is. The thumbnail answers that, and `meta()` says what kind of file it is. */
            ->map(fn (Media $medium) => [
                'id' => $medium->id,
                'title' => $medium->name,
                'meta' => $medium->meta(),
                'thumb' => $medium->thumbUrl(),
                'isImage' => str_starts_with($medium->mime, 'image/'),
                'href' => "/cms/media?selected={$medium->id}",
            ])
            ->all();
    }

    /**
     * Searchable by who sent it and where they are, never by the message. Somebody's account of
     * their own circumstances is not an index for a colleague to browse; finding the sender is
     * what the inbox is for.
     */
    private function enquiries(string $term): array
    {
        return Enquiry::query()
            ->where(fn (Builder $q) => $this->match($q, $term, ['name', 'email', 'suburb']))
            ->orderByDesc('id')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Enquiry $enquiry) => [
                'id' => $enquiry->id,
                'title' => $enquiry->name,
                'meta' => $enquiry->status === Enquiry::DEALT_WITH ? null : $enquiry->statusLabel(),
                'href' => '/cms/enquiries',
            ])
            ->all();
    }

    /**
     * `%` and `_` are wildcards to LIKE, so a term containing either has to be escaped or a
     * search for "50%" quietly matches every row.
     *
     * The `ESCAPE` clause is not optional: SQLite has no default escape character, so escaping
     * without declaring one leaves the wildcards live and passes the backslash through as a
     * literal to match on. The column names are this class's own constants, never user input.
     */
    private function match(Builder $query, string $term, array $columns): void
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

        foreach ($columns as $column) {
            $query->orWhereRaw("{$column} like ? escape '!'", ["%{$escaped}%"]);
        }
    }
}
