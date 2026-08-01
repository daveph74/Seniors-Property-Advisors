<?php

namespace App\Providers;

use App\Auth\Permissions;
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
    }
}
