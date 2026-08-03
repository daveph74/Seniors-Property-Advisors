<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\Cms\AccountController;
use App\Http\Controllers\Cms\ActivityController;
use App\Http\Controllers\Cms\BlogController as CmsBlogController;
use App\Http\Controllers\Cms\CmsPageController;
use App\Http\Controllers\Cms\DeletedContentController;
use App\Http\Controllers\Cms\FaqController;
use App\Http\Controllers\Cms\MediaController;
use App\Http\Controllers\Cms\NavigationController;
use App\Http\Controllers\Cms\ReusableSectionController;
use App\Http\Controllers\Cms\TestimonialController;
use App\Http\Controllers\Cms\UserController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [PageController::class, 'home'])->name('agent-finder');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| CMS admin
|--------------------------------------------------------------------------
| Everything here needs an active CMS account. `permit:content.manage` covers
| both roles; the tighter abilities are super-administrator only (scope §2).
*/
Route::prefix('cms')->name('cms.')->middleware(['permit:content.manage', 'auth.session'])->group(function () {
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::patch('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');

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
    Route::post('/pages/{page}/unarchive', [CmsPageController::class, 'unarchive'])
        ->middleware('permit:content.restore')->name('pages.unarchive');
    Route::post('/pages/{page}/duplicate', [CmsPageController::class, 'duplicate'])->name('pages.duplicate');

    Route::get('/reusable-sections/{reusable}', [ReusableSectionController::class, 'show'])
        ->whereNumber('reusable')->name('reusable.show');
    Route::post('/reusable-sections', [ReusableSectionController::class, 'store'])->name('reusable.store');
    Route::delete('/reusable-sections/{reusable}', [ReusableSectionController::class, 'destroy'])
        ->whereNumber('reusable')->middleware('permit:content.delete')->name('reusable.destroy');

    Route::get('/blog', [CmsBlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/new', [CmsBlogController::class, 'create'])->name('blog.create');
    Route::post('/blog', [CmsBlogController::class, 'store'])->name('blog.store');
    Route::get('/blog/{post}/edit', [CmsBlogController::class, 'edit'])->whereNumber('post')->name('blog.edit');
    Route::patch('/blog/{post}', [CmsBlogController::class, 'update'])->whereNumber('post')->name('blog.update');
    Route::get('/blog/{post}/preview', [CmsBlogController::class, 'preview'])->whereNumber('post')->name('blog.preview');
    Route::post('/blog/{post}/publish', [CmsBlogController::class, 'publish'])->whereNumber('post')->name('blog.publish');
    Route::post('/blog/{post}/unpublish', [CmsBlogController::class, 'unpublish'])->whereNumber('post')->name('blog.unpublish');
    Route::post('/blog/{post}/archive', [CmsBlogController::class, 'archive'])->whereNumber('post')->name('blog.archive');
    Route::post('/blog/{post}/unarchive', [CmsBlogController::class, 'unarchive'])
        ->whereNumber('post')->middleware('permit:content.restore')->name('blog.unarchive');
    Route::post('/blog/{post}/duplicate', [CmsBlogController::class, 'duplicate'])->whereNumber('post')->name('blog.duplicate');
    Route::delete('/blog/{post}', [CmsBlogController::class, 'destroy'])
        ->whereNumber('post')->middleware('permit:content.delete')->name('blog.destroy');
    Route::post('/blog-categories', [CmsBlogController::class, 'storeCategory'])->name('blog.categories.store');
    Route::patch('/blog-categories/{category}', [CmsBlogController::class, 'updateCategory'])->name('blog.categories.update');
    Route::post('/blog-categories/reorder', [CmsBlogController::class, 'reorderCategories'])->name('blog.categories.reorder');
    Route::delete('/blog-categories/{category}', [CmsBlogController::class, 'destroyCategory'])
        ->middleware('permit:content.delete')->name('blog.categories.destroy');

    Route::get('/faqs', [FaqController::class, 'index'])->name('faqs.index');
    Route::post('/faqs', [FaqController::class, 'store'])->name('faqs.store');
    Route::post('/faqs/reorder', [FaqController::class, 'reorder'])->name('faqs.reorder');
    Route::patch('/faqs/{faq}', [FaqController::class, 'update'])->name('faqs.update');
    Route::delete('/faqs/{faq}', [FaqController::class, 'destroy'])
        ->middleware('permit:content.delete')->name('faqs.destroy');
    Route::post('/faq-categories', [FaqController::class, 'storeCategory'])->name('faqs.categories.store');
    Route::post('/faq-categories/reorder', [FaqController::class, 'reorderCategories'])->name('faqs.categories.reorder');
    Route::patch('/faq-categories/{category}', [FaqController::class, 'updateCategory'])->name('faqs.categories.update');
    Route::delete('/faq-categories/{category}', [FaqController::class, 'destroyCategory'])
        ->middleware('permit:content.delete')->name('faqs.categories.destroy');
    Route::get('/testimonials', [TestimonialController::class, 'index'])->name('testimonials.index');
    Route::post('/testimonials', [TestimonialController::class, 'store'])->name('testimonials.store');
    Route::post('/testimonials/reorder', [TestimonialController::class, 'reorder'])->name('testimonials.reorder');
    Route::patch('/testimonials/{testimonial}', [TestimonialController::class, 'update'])->name('testimonials.update');
    Route::post('/testimonials/{testimonial}/consent', [TestimonialController::class, 'consent'])->name('testimonials.consent');
    Route::delete('/testimonials/{testimonial}', [TestimonialController::class, 'destroy'])
        ->middleware('permit:content.delete')->name('testimonials.destroy');
    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
    Route::get('/deleted', [DeletedContentController::class, 'index'])
        ->middleware('permit:content.restore')->name('deleted.index');
    Route::post('/deleted/{kind}/{id}/restore', [DeletedContentController::class, 'restore'])
        ->whereNumber('id')->middleware('permit:content.restore')->name('deleted.restore');
    Route::delete('/deleted/{kind}/{id}', [DeletedContentController::class, 'destroy'])
        ->whereNumber('id')->middleware('permit:content.delete')->name('deleted.destroy');
    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::get('/media/library', [MediaController::class, 'library'])->name('media.library');
    Route::post('/media/sign', [MediaController::class, 'sign'])->name('media.sign');
    Route::post('/media', [MediaController::class, 'store'])->name('media.store');
    Route::post('/media/usage', [MediaController::class, 'usageFor'])->name('media.usage');
    Route::patch('/media/{medium}', [MediaController::class, 'update'])
        ->whereNumber('medium')->name('media.update');
    Route::delete('/media', [MediaController::class, 'destroyMany'])
        ->middleware('permit:content.delete')->name('media.destroy-many');
    Route::delete('/media/{medium}', [MediaController::class, 'destroy'])
        ->whereNumber('medium')->middleware('permit:content.delete')->name('media.destroy');
    Route::get('/navigation', [NavigationController::class, 'index'])->name('navigation.index');
    Route::put('/navigation', [NavigationController::class, 'update'])->name('navigation.update');
    Route::get('/global-content', fn () => Inertia::render('Cms/Global/Index'))->name('global.index');

    Route::middleware('permit:users.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::get('/settings', fn () => Inertia::render('Cms/Settings/Index'))
        ->middleware('permit:settings.manage')->name('settings.index');
});

Route::get('/media/{key}', [MediaController::class, 'show'])
    ->where('key', '.*')->name('media.show');

/*
| The listing at /blog is an ordinary CMS page holding a blog-list section, so it is
| resolved by the catch-all below. These two claim the /blog/* namespace, which is why
| "articles" is a reserved article slug and a page cannot be given a blog/... slug.
*/
Route::get('/blog/articles', [BlogController::class, 'articles'])->name('blog.articles');

/* Throttled like the sign-in form: a public endpoint that writes a row invites a script. */
Route::post('/enquiries', [EnquiryController::class, 'store'])
    ->middleware('throttle:6,1')->name('enquiries.store');
Route::get('/blog/{article}', [BlogController::class, 'show'])
    ->where('article', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('blog.show');

Route::redirect('/home', '/', 301);

Route::get('/{path}', [PageController::class, 'show'])
    ->where('path', '(?!(?:cms|build|storage|up)(?:/|$))[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*)*')
    ->name('page.show');
