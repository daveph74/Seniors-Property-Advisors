<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Search;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The header's search box. JSON rather than an Inertia visit: it answers while somebody is still
 * typing, and a partial word should not become a page in the browser's history.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, Search $search): JsonResponse
    {
        $term = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ])['q'] ?? '';

        return response()->json(['groups' => $search->for($term)]);
    }
}
