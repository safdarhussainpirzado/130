<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireFreshMfa
{
    /**
     * Maximum freshness window in seconds (5 minutes configurable default).
     */
    private const FRESHNESS_WINDOW = 300;

    public function handle(Request $request, Closure $next, ?string $purpose = 'sensitive_action'): Response
    {
        $lastReauth = (int) $request->session()->get('sensitive_reauth_at', 0);
        $boundPurpose = (string) $request->session()->get('sensitive_reauth_purpose', '');

        $isFresh = ($lastReauth > 0) && ((now()->timestamp - $lastReauth) <= self::FRESHNESS_WINDOW);
        $purposeMatches = empty($boundPurpose) || ($boundPurpose === $purpose);

        if (!$isFresh || !$purposeMatches) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'error'    => 'REAUTHENTICATION_REQUIRED',
                    'message'  => 'Recent MFA re-authentication is required to perform this sensitive action.',
                    'purpose'  => $purpose,
                ], 403);
            }

            // Save intended target and purpose
            $request->session()->put('reauth_intended_url', $request->fullUrl());
            $request->session()->put('reauth_intended_method', $request->method());
            $request->session()->put('reauth_purpose', $purpose);

            return redirect()->route('auth.reauthenticate.show')
                ->with('warning', 'For your security, please verify your credentials to confirm this sensitive action.');
        }

        return $next($request);
    }
}
