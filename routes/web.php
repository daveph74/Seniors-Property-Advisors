<?php

use App\Http\Controllers\Cms\CmsPageController;
use App\Http\Controllers\Cms\FaqController;
use App\Http\Controllers\Cms\MediaController;
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
    Route::post('/pages', [CmsPageController::class, 'store'])->name('pages.store');
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
    Route::post('/pages/{page}/publish-now', [CmsPageController::class, 'publishNow'])->name('pages.publish-now');
    Route::post('/pages/{page}/unpublish', [CmsPageController::class, 'unpublish'])->name('pages.unpublish');
    Route::post('/pages/{page}/archive', [CmsPageController::class, 'archive'])->name('pages.archive');
    Route::post('/pages/{page}/unarchive', [CmsPageController::class, 'unarchive'])->name('pages.unarchive');
    Route::post('/pages/{page}/duplicate', [CmsPageController::class, 'duplicate'])->name('pages.duplicate');

    Route::get('/reusable-sections/{reusable}', [ReusableSectionController::class, 'show'])
        ->whereNumber('reusable')->name('reusable.show');
    Route::post('/reusable-sections', [ReusableSectionController::class, 'store'])->name('reusable.store');
    Route::delete('/reusable-sections/{reusable}', [ReusableSectionController::class, 'destroy'])
        ->whereNumber('reusable')->name('reusable.destroy');

    Route::get('/blog', fn () => Inertia::render('Cms/Blog/Index'))->name('blog.index');
    Route::get('/faqs', [FaqController::class, 'index'])->name('faqs.index');
    Route::post('/faqs', [FaqController::class, 'store'])->name('faqs.store');
    Route::post('/faqs/reorder', [FaqController::class, 'reorder'])->name('faqs.reorder');
    Route::patch('/faqs/{faq}', [FaqController::class, 'update'])->name('faqs.update');
    Route::delete('/faqs/{faq}', [FaqController::class, 'destroy'])->name('faqs.destroy');
    Route::post('/faq-categories', [FaqController::class, 'storeCategory'])->name('faqs.categories.store');
    Route::patch('/faq-categories/{category}', [FaqController::class, 'updateCategory'])->name('faqs.categories.update');
    Route::get('/testimonials', fn () => Inertia::render('Cms/Testimonials/Index'))->name('testimonials.index');
    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::get('/media/library', [MediaController::class, 'library'])->name('media.library');
    Route::post('/media/sign', [MediaController::class, 'sign'])->name('media.sign');
    Route::post('/media', [MediaController::class, 'store'])->name('media.store');
    Route::post('/media/usage', [MediaController::class, 'usageFor'])->name('media.usage');
    Route::delete('/media', [MediaController::class, 'destroyMany'])->name('media.destroy-many');
    Route::delete('/media/{medium}', [MediaController::class, 'destroy'])
        ->whereNumber('medium')->name('media.destroy');
    Route::get('/navigation', fn () => Inertia::render('Cms/Navigation/Index'))->name('navigation.index');
    Route::get('/global-content', fn () => Inertia::render('Cms/Global/Index'))->name('global.index');
    Route::get('/users', fn () => Inertia::render('Cms/Users/Index'))->name('users.index');
    Route::get('/settings', fn () => Inertia::render('Cms/Settings/Index'))->name('settings.index');
});

Route::get('/media/{key}', [MediaController::class, 'show'])
    ->where('key', '.*')->name('media.show');

Route::redirect('/home', '/', 301);

Route::get('/{path}', [PageController::class, 'show'])
    ->where('path', '(?!(?:cms|build|storage|up)(?:/|$))[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*)*')
    ->name('page.show');
