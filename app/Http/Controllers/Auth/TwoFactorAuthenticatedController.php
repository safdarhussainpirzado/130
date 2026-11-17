<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LoginLog;
use App\Models\User;
use App\Services\TwoFactor\TotpService;
use App\Services\TwoFactor\WebAuthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TwoFactorAuthenticatedController extends Controller
{
    public function __construct(
        private TotpService $totpService,
        private WebAuthnService $webAuthnService
    ) {}

    /**
     * Show the multi-factor authentication challenge screen.
     */
    public function showChallenge(Request $request): View|RedirectResponse
    {
        $userId = $request->session()->get('mfa_pending_user_id');
        $expiresAt = (int) $request->session()->get('mfa_challenge_expires_at', 0);

        if (! $userId || now()->timestamp > $expiresAt) {
            $request->session()->forget([
                'mfa_pending_user_id',
                'mfa_challenge_id',
                'mfa_challenge_expires_at',
                'mfa_attempts',
                'mfa_credential_version',
            ]);

            return redirect()->route('login')->withErrors([
                'username' => 'MFA challenge timed out. Please sign in again.',
            ]);
        }

        /** @var User|null $user */
        $user = User::find($userId);
        if (! $user || ! $user->is_active) {
            return redirect()->route('login')->withErrors([
                'username' => 'Account is unavailable. Please contact the administrator.',
            ]);
        }

        $requiresWebAuthn = $user->requiresWebAuthn();
        $hasWebAuthn = $user->activeWebAuthnCredentials()->exists();
        $hasTotp = ! empty($user->totp_secret);

        return view('auth.two-factor-challenge', [
            'user' => $user,
            'requiresWebAuthn' => $requiresWebAuthn,
            'hasWebAuthn' => $hasWebAuthn,
            'hasTotp' => $hasTotp,
            'attemptsLeft' => max(0, 5 - (int) $request->session()->get('mfa_attempts', 0)),
        ]);
    }

    /**
     * Verify a 6-digit TOTP code during challenge.
     */
    public function verifyTotp(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ]);

        $userId = (int) $request->session()->get('mfa_pending_user_id');
        $expiresAt = (int) $request->session()->get('mfa_challenge_expires_at', 0);

        if (! $userId || now()->timestamp > $expiresAt) {
            $this->terminateChallenge($request, 'Challenge expired');

            return redirect()->route('login')->withErrors([
                'username' => 'Authentication challenge expired. Please sign in again.',
            ]);
        }

        /** @var User|null $user */
        $user = User::find($userId);
        if (! $user || ! $user->is_active) {
            $this->terminateChallenge($request, 'User inactive');

            return redirect()->route('login')->withErrors([
                'username' => 'Account is inactive. Please contact the administrator.',
            ]);
        }

        // Privileged assurance policy check: never allow TOTP for accounts requiring WebAuthn
        if ($user->requiresWebAuthn()) {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => '2fa_downgrade_blocked',
                'table_name' => 'users',
                'record_id' => $user->id,
                'new_values' => ['attempted_method' => 'totp', 'required_method' => 'webauthn'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors([
                'code' => 'Privileged accounts must authenticate using a phishing-resistant WebAuthn security key. TOTP is not permitted.',
            ]);
        }

        // Layered rate limiting: max 5 failed attempts per challenge
        $attempts = (int) $request->session()->get('mfa_attempts', 0) + 1;
        $request->session()->put('mfa_attempts', $attempts);

        if ($attempts > 5) {
            $this->terminateChallenge($request, 'Max challenge attempts exceeded');
            AuditLog::create([
                'user_id' => $user->id,
                'action' => '2fa_challenge_locked',
                'table_name' => 'users',
                'record_id' => $user->id,
                'new_values' => ['reason' => 'Exceeded 5 submission attempts'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('login')->withErrors([
                'username' => 'Too many failed authentication attempts. Please try again from the beginning.',
            ]);
        }

        $result = $this->totpService->verifyCode($user->id, (string) $request->input('code'));

        if (! $result['success']) {
            $action = ($result['reason'] === 'REPLAY_DETECTED') ? '2fa_replayed' : '2fa_failed';
            AuditLog::create([
                'user_id' => $user->id,
                'action' => $action,
                'table_name' => 'users',
                'record_id' => $user->id,
                'new_values' => ['reason' => $result['reason'], 'attempts' => $attempts],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $message = ($result['reason'] === 'REPLAY_DETECTED')
                ? 'This authentication code has already been used. Please wait for the next time-step.'
                : 'The provided verification code is invalid. Please try again.';

            return back()->withErrors(['code' => $message]);
        }

        // Verification successful -> establish authenticated session
        return $this->establishAuthenticatedSession($request, $user, 'totp');
    }

    /**
     * Get WebAuthn options for authentication assertion ceremony.
     */
    public function getWebAuthnOptions(Request $request): JsonResponse
    {
        $userId = (int) $request->session()->get('mfa_pending_user_id');
        $user = User::find($userId);

        if (! $user || ! $user->is_active) {
            return response()->json(['error' => 'CHALLENGE_EXPIRED'], 403);
        }

        $authData = $this->webAuthnService->generateAuthenticationArgs($user);
        if (! $authData['options']) {
            return response()->json(['error' => 'NO_WEBAUTHN_CREDENTIALS'], 400);
        }

        $request->session()->put('webauthn_auth_challenge', $authData['challenge']);

        return response()->json($authData['options']);
    }

    /**
     * Verify WebAuthn assertion signature.
     */
    public function verifyWebAuthn(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'string'],
            'clientDataJSON' => ['required', 'string'],
            'authenticatorData' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ]);

        $userId = (int) $request->session()->get('mfa_pending_user_id');
        $user = User::find($userId);

        if (! $user || ! $user->is_active) {
            return response()->json(['error' => 'CHALLENGE_EXPIRED'], 403);
        }

        $challenge = (string) $request->session()->get('webauthn_auth_challenge');
        if (empty($challenge)) {
            return response()->json(['error' => 'MISSING_CHALLENGE'], 400);
        }

        $request->session()->forget('webauthn_auth_challenge');

        $result = $this->webAuthnService->processAuthentication(
            $user,
            (string) $request->input('id'),
            (string) $request->input('clientDataJSON'),
            (string) $request->input('authenticatorData'),
            (string) $request->input('signature'),
            $challenge
        );

        if (! $result['success']) {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'webauthn_failed',
                'table_name' => 'webauthn_credentials',
                'record_id' => null,
                'new_values' => ['error' => $result['error']],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'error' => 'VERIFICATION_FAILED',
                'message' => $result['error'] ?? 'WebAuthn assertion failed.',
            ], 422);
        }

        // Establish fully authenticated session
        $this->establishAuthenticatedSession($request, $user, 'webauthn');

        return response()->json([
            'success' => true,
            'redirect' => $user->getLandingPageRoute(),
        ]);
    }

    /**
     * Establish State 4: Fully Authenticated Session.
     */
    private function establishAuthenticatedSession(Request $request, User $user, string $method): RedirectResponse
    {
        // Re-evaluate Geo / ASN Policy upon 2FA step (in case client IP changed between password and 2FA)
        $geoPolicy = app(\App\Services\Geo\GeoPolicyEngine::class);
        $geoEval = $geoPolicy->evaluate($user, $request);
        // Lets the Login-event listener skip its own (geo-less) duplicate row; the controller writes the enriched one.
        $request->attributes->set('geo_eval', $geoEval);

        if ($geoEval['should_block']) {
            $this->terminateChallenge($request, 'Geo policy denied access post-2FA');
            return redirect()->route('login')->withErrors([
                'username' => 'Access denied: Logins to the NHMP 130 Helpline are restricted to authorized operational locations.',
            ]);
        }

        if ($geoEval['device_cookie'] !== null) {
            cookie()->queue($geoEval['device_cookie']);
        }

        // Rotate session ID to eliminate session fixation
        $request->session()->regenerate();

        $this->clearChallengeState($request);

        // Login to web guard
        Auth::guard('web')->login($user, false);

        // Store server-side session metadata
        $now = now()->timestamp;
        $request->session()->put([
            '2fa_verified' => true,
            'auth_method' => $method,
            'mfa_completed_at' => $now,
            'security_version' => (int) $user->security_version,
            'last_activity_at' => $now,
            'session_created_at' => $now,
            'sensitive_reauth_at' => $now,
            'recovery_restricted' => false,
        ]);

        // Forensic audit logging with geo & risk signals
        LoginLog::create([
            'user_id'         => $user->id,
            'ip_address'      => $request->ip(),
            'user_agent'      => $request->userAgent(),
            'login_at'        => now(),
            ...LoginLog::geoAttributes($geoEval),
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => ($method === 'webauthn') ? 'webauthn_verified' : '2fa_verified',
            'table_name' => 'users',
            'record_id' => $user->id,
            'new_values' => ['method' => $method, 'security_version' => $user->security_version],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'failed_attempts' => 0,
        ]);

        // Safe internal destination redirect (prevents open redirects)
        $intended = $request->session()->pull('url.intended');
        if ($intended && str_starts_with($intended, config('app.url'))) {
            return redirect($intended);
        }

        return redirect()->to($user->getLandingPageRoute());
    }

    private function clearChallengeState(Request $request): void
    {
        $request->session()->forget([
            'mfa_pending_user_id',
            'mfa_challenge_id',
            'mfa_challenge_expires_at',
            'mfa_attempts',
            'mfa_credential_version',
            'mfa_required_level',
            'mfa_status',
        ]);
    }

    private function terminateChallenge(Request $request, string $reason): void
    {
        $this->clearChallengeState($request);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
