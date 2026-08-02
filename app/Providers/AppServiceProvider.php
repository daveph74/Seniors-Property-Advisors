<?php

namespace App\Providers;

use App\Auth\Permissions;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\Media;
use App\Models\Page;
use App\Models\Testimonial;
use App\Observers\RecordsActivity;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (array_keys(Permissions::ABILITIES) as $ability) {
            Gate::define($ability, fn ($user) => Permissions::allows($user, $ability));
        }

        /* Scope §13. Watched on the model rather than recorded from each controller, so a route
           nobody remembered still leaves a trail. */
        foreach ([Page::class, BlogPost::class, Faq::class, Testimonial::class, Media::class] as $model) {
            $model::observe(RecordsActivity::class);
        }
    }
}
