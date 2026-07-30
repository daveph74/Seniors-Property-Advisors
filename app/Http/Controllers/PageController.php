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
        return $this->render($this->store->resolve('home'));
    }

    public function show(string $path): Response
    {
        return $this->render($this->store->resolve($path));
    }

    private function render(?array $page): Response
    {
        abort_if($page === null, 404);

        return Inertia::render('AgentFinder', [
            'title' => $page['title'],
            'seo' => $page['seo'],
            'sections' => $page['sections'],
            'globals' => $this->store->globals(),
        ]);
    }
}
