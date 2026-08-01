<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Cms/Faqs/Index', [
            'faqs' => Faq::with('category')->ordered()->get()->map(fn (Faq $f) => [
                'id' => $f->id,
                'question' => $f->question,
                'answer' => $f->answer,
                'categoryId' => $f->faq_category_id,
                'category' => $f->category?->name,
                'pageSlug' => $f->page_slug,
                'active' => $f->active,
                'sortOrder' => $f->sort_order,
                'updatedBy' => $f->last_updated_by,
                'updatedAt' => $f->updated_at?->toDateString(),
            ])->all(),
            'categories' => FaqCategory::orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (FaqCategory $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'active' => $c->active,
                    'count' => $c->faqs()->count(),
                ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Faq::create($data + [
            'sort_order' => (int) Faq::max('sort_order') + 1,
            'last_updated_by' => $this->author(),
        ]);

        return back();
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $faq->update($this->validated($request) + ['last_updated_by' => $this->author()]);

        return back();
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return back();
    }

    /**
     * The given questions are shuffled between the positions they already occupy, rather than being
     * numbered from one — otherwise a list sent while the screen is searched or filtered by category
     * overwrites the positions of every question it does not mention.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $ids = array_values(array_filter((array) $request->input('ids', [])));
        $slots = Faq::whereKey($ids)->pluck('sort_order')->sort()->values();

        foreach ($ids as $position => $id) {
            Faq::whereKey($id)->update(['sort_order' => $slots[$position] ?? $position + 1]);
        }

        return back();
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        FaqCategory::create([
            'name' => trim(strip_tags((string) $request->input('name'))),
            'sort_order' => (int) FaqCategory::max('sort_order') + 1,
        ]);

        return back();
    }

    public function updateCategory(Request $request, FaqCategory $category): RedirectResponse
    {
        $category->update(array_filter([
            'name' => $request->filled('name') ? trim(strip_tags((string) $request->input('name'))) : null,
            'active' => $request->has('active') ? $request->boolean('active') : null,
        ], fn ($v) => $v !== null));

        return back();
    }

    public function reorderCategories(Request $request): RedirectResponse
    {
        foreach ((array) $request->input('ids', []) as $position => $id) {
            FaqCategory::whereKey($id)->update(['sort_order' => $position + 1]);
        }

        return back();
    }

    /**
     * The questions are kept. `faq_category_id` is nullable with nullOnDelete, so they simply
     * become uncategorised and still answer on any page that pulls all FAQs — deleting a
     * grouping should never delete the content filed under it.
     */
    public function destroyCategory(FaqCategory $category): RedirectResponse
    {
        $category->delete();

        return back();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:300'],
            'answer' => ['required', 'string', 'max:4000'],
            'faq_category_id' => ['nullable', 'integer', 'exists:faq_categories,id'],
            'page_slug' => ['nullable', 'string', 'max:190'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $data['question'] = trim(strip_tags($data['question']));
        $data['answer'] = trim(strip_tags($data['answer']));

        return $data;
    }

    private function author(): string
    {
        return (string) request()->user()->name;
    }
}
