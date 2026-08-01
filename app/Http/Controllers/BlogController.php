<?php

namespace App\Http\Controllers;

use App\Content\ContentLibrary;
use App\Content\PageContentStore;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function __construct(
        private readonly PageContentStore $store,
        private readonly ContentLibrary $library,
    ) {}

    public function show(string $article): Response
    {
        $post = BlogPost::published()->with('categories')->where('slug', $article)->first();

        abort_if($post === null, 404);

        return Inertia::render('Article', [
            'article' => $post->toArticle(),
            'related' => $this->library->related($post),
            'globals' => $this->store->globals(),
        ]);
    }

    /**
     * The next page of cards for the listing's load-more control. Kept separate from the
     * page payload so a site with many articles does not ship them all on every render.
     */
    public function articles(): JsonResponse
    {
        $page = max(1, (int) request()->integer('page', 1));

        return response()->json($this->library->posts($page));
    }
}
