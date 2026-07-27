<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('AgentFinder'))->name('agent-finder');

/*
|--------------------------------------------------------------------------
| CMS admin (prototype)
|--------------------------------------------------------------------------
| Renders the CMS UI with mocked data — no persistence layer yet. Each
| route just hands the matching Inertia page component its static props.
*/
Route::prefix('cms')->name('cms.')->group(function () {
    Route::get('/', fn () => Inertia::render('Cms/Dashboard'))->name('dashboard');

    Route::get('/pages', fn () => Inertia::render('Cms/Pages/Index'))->name('pages.index');
    Route::get('/pages/{page}/edit', fn ($page) => Inertia::render('Cms/Pages/Builder', ['pageId' => $page]))->name('pages.edit');

    Route::get('/blog', fn () => Inertia::render('Cms/Blog/Index'))->name('blog.index');
    Route::get('/faqs', fn () => Inertia::render('Cms/Faqs/Index'))->name('faqs.index');
    Route::get('/testimonials', fn () => Inertia::render('Cms/Testimonials/Index'))->name('testimonials.index');
    Route::get('/media', fn () => Inertia::render('Cms/Media/Index'))->name('media.index');
    Route::get('/navigation', fn () => Inertia::render('Cms/Navigation/Index'))->name('navigation.index');
    Route::get('/global-content', fn () => Inertia::render('Cms/Global/Index'))->name('global.index');
    Route::get('/users', fn () => Inertia::render('Cms/Users/Index'))->name('users.index');
    Route::get('/settings', fn () => Inertia::render('Cms/Settings/Index'))->name('settings.index');
});
