<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordNotExpired
{
    /**
     * Route name exemptions.
     */
    private const EXEMPT_ROUTES = [
        'password.expired',
        'password.expired.update',
        'logout',
        'login',
        'auth.heartbeat',
    ];

    /**
     * URI pattern exemptions.
     */
    private const EXEMPT_PATTERNS = [
        'password/expired*',
        'logout*',
        'login*',
        'auth/heartbeat*',
        'up',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        // Only enforce for authenticated users
        if (! $user) {
            return $next($request);
        }

        // Check URI pattern exemptions
        if ($request->is(...self::EXEMPT_PATTERNS)) {
            return $next($request);
        }

        $currentRoute = $request->route()?->getName();

        if ($currentRoute && in_array($currentRoute, self::EXEMPT_ROUTES, true)) {
            return $next($request);
        }

        // Check if password has expired (defaults to 15 days)
        if ($user instanceof \App\Models\User && $user->isPasswordExpired()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'error' => 'PASSWORD_EXPIRED',
                    'message' => 'Your password has expired. In accordance with the mandatory NHMP 15-day cyber security policy, you must set a new password to continue.',
                    'redirect' => route('password.expired'),
                ], 403);
            }

            return redirect()->route('password.expired');
        }

        return $next($request);
    }
}
