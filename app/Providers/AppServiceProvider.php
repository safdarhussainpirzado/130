<?php

namespace App\Providers;

use App\Listeners\LogSuccessfulLogin;
use App\Models\Call;
use App\Models\SupportRequest;
use App\Observers\CallObserver;
use App\Policies\CallPolicy;
use App\Policies\SupportRequestPolicy;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
            URL::forceScheme('https');
        }

        // Push Worker Heartbeat (3-minute TTL in Redis)
        Queue::looping(function () {
            try {
                Redis::set('push:worker:heartbeat', now()->toIso8601String(), 'EX', 180);
            } catch (\Throwable) {
                // Non-fatal if Redis temporarily unavailable
            }
        });

        Call::observe(CallObserver::class);

        Event::listen(
            Login::class,
            LogSuccessfulLogin::class
        );

        Gate::policy(Call::class, CallPolicy::class);
        Gate::policy(SupportRequest::class, SupportRequestPolicy::class);

        Gate::before(function ($user, $ability) {
            if (isset($user->is_active) && ! $user->is_active) {
                return false;
            }
            if ($user->hasPermission($ability)) {
                return true;
            }

            return null;
        });

        // Enforce strong password policy globally
        Password::defaults(function () {
            return Password::min(12)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised();
        });
    }
}
