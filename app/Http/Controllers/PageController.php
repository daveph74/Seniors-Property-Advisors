<?php

namespace App\Http\Controllers;

use App\Content\PageContentStore;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function __construct(private readonly PageContentStore $store) {}

    public function home(): Response
    {
        $page = $this->store->resolve('home');

        abort_if($page === null, 404);

        return Inertia::render('AgentFinder', [
            'title' => $page['title'],
            'seo' => $page['seo'],
            'sections' => $page['sections'],
            'globals' => $this->store->globals(),
        ]);
    }
}
