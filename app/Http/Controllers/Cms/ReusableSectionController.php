<?php

namespace App\Http\Controllers\Cms;

use App\Content\ReusableSectionStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveReusableSectionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ReusableSectionController extends Controller
{
    public function __construct(private readonly ReusableSectionStore $store) {}

    public function show(int $reusable): JsonResponse
    {
        $block = $this->store->block($reusable);

        abort_if($block === null, 404);

        return response()->json($block);
    }

    public function store(SaveReusableSectionRequest $request): RedirectResponse
    {
        $this->store->save($request->name(), $request->block(), request()->user()?->name ?? 'Helen Marsh');

        return back();
    }

    public function destroy(int $reusable): RedirectResponse
    {
        abort_if(! $this->store->delete($reusable), 404);

        return back();
    }
}
