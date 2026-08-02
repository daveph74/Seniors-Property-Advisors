<?php

namespace App\Http\Controllers\Cms;

use App\Content\Text;
use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    /**
     * These two were the only write paths in the CMS with no validation at all: an empty name
     * created a nameless row in the sidebar, and a long one threw a database error rather than a
     * message. Names are unique so the list cannot fill with three categories called Selling.
     */
    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $this->validatedCategory($request);

        FaqCategory::create($data + ['sort_order' => (int) FaqCategory::max('sort_order') + 1]);

        return back();
    }

    public function updateCategory(Request $request, FaqCategory $category): RedirectResponse
    {
        $data = $this->validatedCategory($request, $category);

        if ($request->has('active')) {
            $data['active'] = $request->boolean('active');
        }

        $category->update($data);

        return back();
    }

    private function validatedCategory(Request $request, ?FaqCategory $existing = null): array
    {
        $request->merge(Text::cleanAll($request->only('name'), ['name']));

        $needed = $existing === null ? ['required'] : ['sometimes', 'required'];

        return $request->validate([
            'name' => [
                ...$needed,
                'string',
                'max:120',
                Rule::unique('faq_categories', 'name')->ignore($existing),
            ],
        ], [
            'name.unique' => 'There is already a category with that name.',
        ]);
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
        /* Stripped first, so `required` judges what will actually be stored — "<hr>" used to pass
           and then be saved as an empty question that still showed on the website. */
        $request->merge(Text::cleanAll($request->only(['question', 'answer', 'page_slug']), [
            'question', 'answer', 'page_slug',
        ]));

        return $request->validate([
            'question' => ['required', 'string', 'max:300'],
            'answer' => ['required', 'string', 'max:4000'],
            'faq_category_id' => ['nullable', 'integer', 'exists:faq_categories,id'],
            'page_slug' => ['nullable', 'string', 'max:190'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }

    private function author(): string
    {
        return (string) request()->user()->name;
    }
}
