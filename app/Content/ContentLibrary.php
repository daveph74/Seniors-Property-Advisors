<?php

namespace App\Content;

use App\Models\Faq;

class ContentLibrary
{
    public function for(?string $slug = null): array
    {
        return ['faqs' => $this->faqs($slug)];
    }

    private function faqs(?string $slug): array
    {
        return Faq::query()
            ->active()
            ->ordered()
            ->with('category')
            ->where(fn ($q) => $q->whereNull('page_slug')->orWhere('page_slug', $slug))
            ->get()
            ->map(fn (Faq $faq) => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'category' => $faq->category?->name,
                'pageSlug' => $faq->page_slug,
            ])
            ->all();
    }
}
