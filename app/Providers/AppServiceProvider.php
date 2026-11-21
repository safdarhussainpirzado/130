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
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Push Worker Heartbeat (3-minute TTL in Redis)
        \Illuminate\Support\Facades\Queue::looping(function () {
            try {
                \Illuminate\Support\Facades\Redis::setex('push:worker:heartbeat', 180, now()->toIso8601String());
            } catch (\Throwable) {
                // Non-fatal if Redis temporarily unavailable
            }
        });

        \App\Models\Call::observe(\App\Observers\CallObserver::class);
        
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Login::class,
            \App\Listeners\LogSuccessfulLogin::class
        );

        Gate::policy(\App\Models\Call::class, \App\Policies\CallPolicy::class);
        Gate::policy(\App\Models\SupportRequest::class, \App\Policies\SupportRequestPolicy::class);

        Gate::before(function ($user, $ability) {
            if (isset($user->is_active) && !$user->is_active) {
                return false;
            }
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
