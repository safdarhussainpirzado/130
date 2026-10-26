<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

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
        if (config('app.force_https')) {
            if (request()->isSecure() || request()->header('X-Forwarded-Proto') === 'https') {
                \Illuminate\Support\Facades\URL::forceScheme('https');
            }
        }

        \App\Models\Call::observe(\App\Observers\CallObserver::class);
        
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Login::class,
            \App\Listeners\LogSuccessfulLogin::class
        );

        Gate::before(function ($user, $ability) {
            if ($user->hasPermission($ability)) {
                return true;
            }
            return null;
        });

        // Enforce strong password policy globally
        \Illuminate\Validation\Rules\Password::defaults(function () {
            return \Illuminate\Validation\Rules\Password::min(12)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised();
        });
    }

}
