<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust only the real hops (front LB 10.1.2.62 + docker network). With '*' a forged leftmost X-Forwarded-For
        // value became $request->ip(); now the client is the right-most UNtrusted address. Override via TRUSTED_PROXIES.
        $middleware->trustProxies(at: array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '10.1.2.62,172.16.0.0/12'))))));
        $middleware->alias([
            'hamsafar.access' => \App\Http\Middleware\EnsureHamsafarAccess::class,
            'mfa.fresh' => \App\Http\Middleware\RequireFreshMfa::class,
        ]);
        $middleware->priority([
            \App\Http\Middleware\TwoFactorEnforcement::class,
            \Illuminate\Auth\Middleware\Authenticate::class,
        ]);
        $middleware->append(\App\Http\Middleware\AssignRequestId::class);
        $middleware->web(append: [
            \App\Http\Middleware\TwoFactorEnforcement::class,
            \App\Http\Middleware\EnsurePasswordNotExpired::class,
            \App\Http\Middleware\PreventBackHistory::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response, $e, $request) {
            if ($response->getStatusCode() === 403 && ! $request->expectsJson()) {
                /** @var \App\Models\User|null $user */
                $user = \Illuminate\Support\Facades\Auth::user();
                if ($user) {
                    $landing = $user->getLandingPageRoute();
                    // Prevent infinite redirect loop if landing page itself is causing the 403 on GET
                    if ($request->url() !== $landing || $request->method() !== 'GET') {
                        return redirect($landing)
                            ->with('error', 'Unauthorized access. You have been redirected to your authorized workspace.');
                    }
                }
            }
            return $response;
        });
    })->create();
