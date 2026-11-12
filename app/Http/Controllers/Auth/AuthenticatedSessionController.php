<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\LoginLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Zero cross-user bleed: whenever the login page is shown (fresh visit, idle timeout, expired session),
     * tell the browser to drop everything the previous person left behind (HTTP cache, localStorage, sessionStorage).
     */
    public function create(): \Illuminate\Http\Response
    {
        return response()->view('auth.login')->header('Clear-Site-Data', '"cache", "storage"');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->validateCredentials();

        // 1. Session fixation + cross-user defense: destroy the old session entirely (drops any previous user's
        //    attributes and `intended` URL), keep `intended` only when the old session had no authenticated user.
        $intended = Auth::guard('web')->check() ? null : $request->session()->get('url.intended');
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if ($intended) {
            $request->session()->put('url.intended', $intended);
        }

        // 2. Clear stale MFA state from prior sessions
        $request->session()->forget([
            'mfa_pending_user_id',
            'mfa_challenge_id',
            'mfa_challenge_expires_at',
            'mfa_attempts',
            'mfa_credential_version',
            'mfa_required_level',
            'mfa_status',
            'recovery_restricted',
            'sensitive_reauth_at',
        ]);

        // 3. Centralized Policy Resolution: Check if MFA is required for this account
        $mfaResolver = app(\App\Services\TwoFactor\MfaPolicyResolver::class);
        $mfaRequired = $mfaResolver->isMfaRequiredForUser($user);

        if (!$mfaRequired) {
            // Role is exempt from MFA! Directly authenticate into web guard.
            // Remember-me is disabled: persistent cookies re-authenticated the previous user on shared workstations.
            Auth::guard('web')->login($user, false);
            $request->session()->regenerate();

            $now = now()->timestamp;
            $request->session()->put([
                'auth_method'        => 'password_only',
                'security_version'   => (int) $user->security_version,
                'last_activity_at'   => $now,
                'session_created_at' => $now,
            ]);

            \App\Models\AuditLog::create([
                'user_id'    => $user->id,
                'action'     => 'login_success_mfa_exempt',
                'table_name' => 'users',
                'record_id'  => $user->id,
                'new_values' => ['role' => $user->role?->name, 'reason' => 'Operational role temporarily exempt from MFA'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            LoginLog::create([
                'user_id'    => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'login_at'   => now(),
            ]);

            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            return redirect()->intended($user->getLandingPageRoute());
        }

        // 4. Establish State 2 (MFA Pending) or State 3 (Enrollment Required)
        $challengeId = \Illuminate\Support\Str::random(40);
        $expiresAt = now()->addMinutes(5)->timestamp; // 5-minute login challenge lifetime

        $request->session()->put([
            'mfa_pending_user_id'      => $user->id,
            'mfa_challenge_id'         => $challengeId,
            'mfa_challenge_expires_at' => $expiresAt,
            'mfa_attempts'             => 0,
            'mfa_credential_version'   => (int) $user->security_version,
            'mfa_required_level'       => $user->requiresWebAuthn() ? 'webauthn' : 'totp',
        ]);

        // 5. Redirect according to enrollment status
        if (!$user->hasMfaEnrolled()) {
            $request->session()->put('mfa_status', 'enrollment_required');
            return redirect()->route('2fa.enroll.required');
        }

        $request->session()->put('mfa_status', 'challenge_pending');
        return redirect()->route('2fa.challenge');
    }

    public function destroy(Request $request): RedirectResponse
    {
        // ── Forensic logout audit ──────────────────────────────────────
        if (Auth::check()) {
            $userId = Auth::id();
            LoginLog::where('user_id', $userId)
                ->whereNull('logout_at')
                ->latest('login_at')
                ->first()
                ?->update(['logout_at' => now()]);

            \App\Models\AuditLog::create([
                'user_id'    => $userId,
                'action'     => 'logout',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Wipe the browser-side state of the person who just logged out (shared workstations).
        return redirect('/')->header('Clear-Site-Data', '"cache", "storage"');
    }
}
