<?php

namespace App\Providers;

use App\Auth\Permissions;
use App\Http\Limits;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\Media;
use App\Models\Page;
use App\Models\Testimonial;
use App\Observers\RecordsActivity;
use Illuminate\Http\Middleware\TrustProxies;
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

        /* Named here so the routes read `throttle:cms-write` rather than a number nobody can weigh
           without knowing what else is set. */
        Limits::define();

        /*
         * Who the visitor is, once something else terminates TLS in front of this.
         *
         * Two things depend on it and both fail silently without it: every limit keyed on an address
         * collapses onto the proxy's, so six enquiries a minute becomes six for the whole internet;
         * and `$request->secure()` stays false, so `SecurityHeaders` never sends HSTS — a header this
         * application has tested since the review and never once delivered.
         *
         * Set here rather than in `bootstrap/app.php` because that closure runs before configuration
         * exists, and `env()` there is empty on a server that has cached its config. Named addresses
         * rather than `*`: trusting whoever connects is only safe while PHP-FPM cannot be reached
         * except through the proxy, and trusted wrongly, a visitor can set their own address and
         * every limit becomes advisory.
         */
        $proxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('app.trusted_proxies')),
        )));

        if ($proxies !== []) {
            TrustProxies::at($proxies);
        }
    }
}
