<?php

use App\Http\Controllers\Cms\CmsPageController;
use App\Http\Controllers\Cms\ReusableSectionController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [PageController::class, 'home'])->name('agent-finder');

/*
|--------------------------------------------------------------------------
| CMS admin (prototype)
|--------------------------------------------------------------------------
| Renders the CMS UI with mocked data — no persistence layer yet. Each
| route just hands the matching Inertia page component its static props.
*/
Route::prefix('cms')->name('cms.')->group(function () {
    Route::get('/', fn () => Inertia::render('Cms/Dashboard'))->name('dashboard');

    Route::get('/pages', [CmsPageController::class, 'index'])->name('pages.index');
    Route::get('/pages/{page}/edit', [CmsPageController::class, 'edit'])->name('pages.edit');
    Route::post('/pages/{page}/draft', [CmsPageController::class, 'saveDraft'])->name('pages.draft');
    Route::patch('/pages/{page}/details', [CmsPageController::class, 'saveDetails'])->name('pages.details');
    Route::post('/pages/{page}/publish', [CmsPageController::class, 'publish'])->name('pages.publish');
    Route::get('/pages/{page}/preview', [CmsPageController::class, 'preview'])->name('pages.preview');
    Route::get('/pages/{page}/preview/{n}', [CmsPageController::class, 'previewRevision'])
        ->whereNumber('n')->name('pages.preview.revision');
    Route::post('/pages/{page}/restore/{n}', [CmsPageController::class, 'restore'])
        ->whereNumber('n')->name('pages.restore');
    Route::get('/pages/{page}/compare/{n}', [CmsPageController::class, 'compare'])
        ->whereNumber('n')->name('pages.compare');
    Route::post('/pages/{page}/changes', [CmsPageController::class, 'changes'])->name('pages.changes');
    Route::get('/pages/{page}/revisions', [CmsPageController::class, 'revisions'])->name('pages.revisions');

    Route::get('/reusable-sections/{reusable}', [ReusableSectionController::class, 'show'])
        ->whereNumber('reusable')->name('reusable.show');
    Route::post('/reusable-sections', [ReusableSectionController::class, 'store'])->name('reusable.store');
    Route::delete('/reusable-sections/{reusable}', [ReusableSectionController::class, 'destroy'])
        ->whereNumber('reusable')->name('reusable.destroy');

    Route::get('/blog', fn () => Inertia::render('Cms/Blog/Index'))->name('blog.index');
    Route::get('/faqs', fn () => Inertia::render('Cms/Faqs/Index'))->name('faqs.index');
    Route::get('/testimonials', fn () => Inertia::render('Cms/Testimonials/Index'))->name('testimonials.index');
    Route::get('/media', fn () => Inertia::render('Cms/Media/Index'))->name('media.index');
    Route::get('/navigation', fn () => Inertia::render('Cms/Navigation/Index'))->name('navigation.index');
    Route::get('/global-content', fn () => Inertia::render('Cms/Global/Index'))->name('global.index');
    Route::get('/users', fn () => Inertia::render('Cms/Users/Index'))->name('users.index');
    Route::get('/settings', fn () => Inertia::render('Cms/Settings/Index'))->name('settings.index');
});
