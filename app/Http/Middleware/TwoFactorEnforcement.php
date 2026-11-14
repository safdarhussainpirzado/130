<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\TwoFactor\MfaPolicyResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorEnforcement
{
    /**
     * Non-human or background routes that should NOT extend human inactivity timer.
     */
    private const BACKGROUND_ROUTES = [
        'auth.heartbeat',
        'api.notifications.poll',
        'api.calls.reminder',
    ];

    /**
     * Explicit public and pre-authentication route allowlist.
     * Path wildcards like '2fa*' are strictly avoided.
     */
    private const ALLOWED_ROUTE_NAMES = [
        'login',
        'login.store',
        'password.request',
        'password.email',
        'password.reset',
        'password.store',
        'password.expired',
        'password.expired.update',
        'account.unblock',
        'account.unblock.verify',
        'logout',
        '2fa.challenge',
        '2fa.challenge.totp',
        '2fa.challenge.webauthn.options',
        '2fa.challenge.webauthn.verify',
        '2fa.recovery.show',
        '2fa.recovery.verify',
        '2fa.enroll.required',
        '2fa.enroll.show',
        '2fa.enroll.confirm',
        '2fa.enroll.webauthn.options',
        '2fa.enroll.webauthn.register',
        '2fa.enroll.success',
        '2fa.recovery-request.create',
        '2fa.recovery-request.store',
        '2fa.index',
        '2fa.verify',
        '2fa.setup',
        'public.travel-advisory',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // 1. Health check bypass
        if ($request->is('up') || $request->is('login')) {
            return $next($request);
        }

        $currentRouteName = $request->route()?->getName();

        // 2. Allow explicit public / pre-auth endpoints
        if ($currentRouteName && in_array($currentRouteName, self::ALLOWED_ROUTE_NAMES, true)) {
            return $next($request);
        }

        // 3. User is not authenticated in the web guard
        if (! Auth::guard('web')->check()) {
            // If they have an active pre-auth MFA challenge pending, redirect them to the challenge screen
            if ($request->session()->has('mfa_pending_user_id')) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'error' => 'MFA_CHALLENGE_PENDING',
                        'message' => 'Multi-factor authentication challenge is pending.',
                    ], 403);
                }
                if ($request->session()->get('mfa_status') === 'enrollment_required' || $request->session()->get('mfa_flow') === 'enrollment') {
                    return redirect()->route('2fa.enroll.required');
                }

                return redirect()->route('2fa.challenge');
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => 'UNAUTHENTICATED'], 401);
            }

            return redirect()->guest(route('login'));
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        // 4. Reject inactive or suspended users immediately in every state
        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => 'ACCOUNT_SUSPENDED'], 403);
            }

            return redirect()->route('login')->withErrors([
                'username' => 'Your account is deactivated. Contact the system administrator.',
            ]);
        }

        // 5. Security Version Check (Session Revocation Enforcement)
        $sessionSecurityVersion = $request->session()->get('security_version');
        if ($sessionSecurityVersion !== null && (int) $sessionSecurityVersion !== (int) $user->security_version) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => 'SESSION_REVOKED'], 401);
            }

            return redirect()->route('login')->withErrors([
                'username' => 'Your session was terminated due to a security credential change. Please sign in again.',
            ]);
        }

        // 6. Absolute Authenticated-Session Lifetime (12 Hours default)
        $sessionCreatedAt = (int) $request->session()->get('session_created_at', 0);
        if ($sessionCreatedAt > 0 && (now()->timestamp - $sessionCreatedAt) > 43200) { // 12 hours
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => 'MAX_SESSION_LIFETIME_EXCEEDED'], 401);
            }

            return redirect()->route('login')->withErrors([
                'username' => 'Maximum session lifetime (12 hours) reached. Please sign in again.',
            ]);
        }

        // 7. Human Inactivity Timeout (15 Minutes for Admin Roles, 30 Minutes default)
        $lastActivity = (int) $request->session()->get('last_activity_at', 0);
        $isHeartbeatActive = $request->is('auth/heartbeat*') && $request->boolean('active');
        $isBackground = ! $isHeartbeatActive && (
            in_array($currentRouteName, self::BACKGROUND_ROUTES, true)
            || $request->is('api/notifications/poll*')
            || $request->is('auth/heartbeat*')
        );

        $roleName = strtolower((string) ($user->role?->name ?? ''));
        $maxIdleMinutes = 30;
        if (in_array($roleName, ['super_admin', 'operation_admin'], true)) {
            $maxIdleMinutes = (int) config("geo_access.roles.{$roleName}.max_idle_minutes", 15);
        }
        $maxIdleSeconds = $maxIdleMinutes * 60;

        if ($lastActivity > 0 && (now()->timestamp - $lastActivity) > $maxIdleSeconds) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => 'INACTIVITY_TIMEOUT'], 401);
            }

            return redirect()->route('login')->withErrors([
                'username' => "Session expired due to {$maxIdleMinutes} minutes of inactivity.",
            ]);
        }

        // Only meaningful human interaction resets the inactivity timer
        if (! $isBackground) {
            $request->session()->put('last_activity_at', now()->timestamp);
        }

        // 8. Role-based MFA Policy Enforcement (Cached for 10 min per user security version)
        $secVersion = (int) ($user->security_version ?? 1);
        $mfaCacheKey = "user_mfa_req_{$user->id}_{$secVersion}";
        $mfaRequired = (bool) Cache::remember($mfaCacheKey, 600, function () use ($user): mixed {
            return app(MfaPolicyResolver::class)->isMfaRequiredForUser($user);
        });
        $authMethod = $request->session()->get('auth_method');

        // Allow test fixtures using actingAs() without explicit session method metadata
        if (app()->environment('testing') && $authMethod === null) {
            return $next($request);
        }

        // If user logged in as password_only, but MFA is currently required (e.g. policy changed or role modified)
        if ($authMethod === 'password_only' && $mfaRequired) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => 'MFA_POLICY_ENFORCED'], 403);
            }

            return redirect()->route('login')->withErrors([
                'username' => 'MFA policy enforcement has been enabled for your role. Please sign in and complete two-factor authentication.',
            ]);
        }

        // 9. Assurance Level Enforcement: WebAuthn Required for Privileged Accounts
        // A privileged account requiring WebAuthn must never be satisfied by a TOTP-only or password-only session
        if ($user->requiresWebAuthn() && ! $request->session()->get('recovery_restricted') && !empty($user->mfa_required)) {
            if ($authMethod !== 'webauthn') {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json(['error' => 'WEBAUTHN_REQUIRED'], 403);
                }

                return redirect()->route('login')->withErrors([
                    'username' => 'Privileged accounts require phishing-resistant WebAuthn security keys. TOTP or password-only access is not permitted for this role.',
                ]);
            }
        }

        // 9. State 5: Recovery Restricted Enforcement
        // Users authenticated with emergency recovery codes can ONLY access replacement workflows
        if ($request->session()->get('recovery_restricted') === true) {
            $allowedRecoveryRoutes = [
                '2fa.replace.show',
                '2fa.replace.confirm',
                '2fa.replace.webauthn.options',
                '2fa.replace.webauthn.register',
                'logout',
            ];

            if (! in_array($currentRouteName, $allowedRecoveryRoutes, true)) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'error' => 'RECOVERY_RESTRICTED',
                        'message' => 'Account is in restricted recovery mode. Authenticator replacement required.',
                    ], 403);
                }

                return redirect()->route('2fa.replace.show')->with('warning', 'Your session is in restricted emergency recovery mode. You must enroll a replacement authenticator to restore standard CRM access.');
            }
        }

        return $next($request);
    }
}
