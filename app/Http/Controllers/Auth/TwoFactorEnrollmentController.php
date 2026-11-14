<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TwoFactor\EnrollmentAuthorizationService;
use App\Services\TwoFactor\TotpService;
use App\Services\TwoFactor\WebAuthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TwoFactorEnrollmentController extends Controller
{
    public function __construct(
        private TotpService $totpService,
        private WebAuthnService $webAuthnService,
        private EnrollmentAuthorizationService $enrollmentAuthService
    ) {}

    /**
     * Show requirement notice when staff attempts login without MFA.
     */
    public function showEnrollmentRequired(Request $request): View|RedirectResponse
    {
        $userId = $request->session()->get('mfa_pending_user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        /** @var User|null $user */
        $user = User::find($userId);
        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-enrollment-required', [
            'user' => $user,
        ]);
    }

    /**
     * Show the first-time enrollment screen with local SVG QR code.
     * Supports both token-authorized enrollment and active verified-login enrollment ceremonies.
     */
    public function showEnroll(Request $request): View|RedirectResponse
    {
        $token = (string) ($request->query('token') ?? $request->session()->get('mfa_enrollment_token'));
        $user = null;
        $authorization = null;

        if (!empty($token)) {
            $authorization = $this->enrollmentAuthService->findValidAuthorization($token);
            if (! $authorization) {
                return redirect()->route('login')->withErrors([
                    'username' => 'The enrollment authorization token is invalid, expired, or has already been used.',
                ]);
            }
            $user = $authorization->user;
        } else {
            // Check if user is in an active login MFA enrollment ceremony
            $userId = (int) ($request->session()->get('mfa_enrollment_user_id') ?: $request->session()->get('mfa_pending_user_id'));
            if ($userId > 0) {
                $user = User::find($userId);
            }
        }

        if (! $user || ! $user->is_active) {
            return redirect()->route('login')->withErrors([
                'username' => 'An active authenticated session or valid enrollment token is required to set up multi-factor authentication.',
            ]);
        }

        // Store active enrollment context in session
        $request->session()->put([
            'mfa_enrollment_token' => $token ?: null,
            'mfa_enrollment_user_id' => $user->id,
            'mfa_enrollment_auth_id' => $authorization?->id,
        ]);

        // Generate an uncommitted pending secret bound to this enrollment ceremony
        $pendingSecret = $request->session()->get('pending_totp_secret');
        if (empty($pendingSecret)) {
            $pendingSecret = $this->totpService->generateSecret();
            $request->session()->put('pending_totp_secret', $pendingSecret);
        }

        // Generate local SVG QR code (zero external requests)
        $qrCodeSvg = $this->totpService->getLocalQrCodeSvg($user->username, $pendingSecret);
        $qrCodeDataUri = $this->totpService->getLocalQrCodeDataUri($user->username, $pendingSecret);

        return view('auth.two-factor-setup', [
            'user' => $user,
            'secret' => $pendingSecret,
            'qrCodeSvg' => $qrCodeSvg,
            'qrCodeDataUri' => $qrCodeDataUri,
            'token' => $token,
            'requiresWebAuthn' => $user->requiresWebAuthn(),
        ]);
    }

    /**
     * Confirm TOTP setup by proving possession of the code.
     */
    public function confirmTotp(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ]);

        $token = (string) $request->session()->get('mfa_enrollment_token');
        $userId = (int) ($request->session()->get('mfa_enrollment_user_id') ?: $request->session()->get('mfa_pending_user_id'));
        $pendingSecret = (string) $request->session()->get('pending_totp_secret');

        if (empty($userId) || empty($pendingSecret)) {
            return redirect()->route('login')->withErrors([
                'username' => 'Enrollment transaction expired. Please restart the authorization process.',
            ]);
        }

        $authorization = null;
        if (!empty($token)) {
            $authorization = $this->enrollmentAuthService->findValidAuthorization($token);
            if (! $authorization || $authorization->user_id !== $userId) {
                return redirect()->route('login')->withErrors([
                    'username' => 'The enrollment authorization has expired or is invalid.',
                ]);
            }
        }

        /** @var User|null $user */
        $user = User::find($userId);
        if (! $user || ! $user->is_active) {
            return redirect()->route('login');
        }

        // Verify code against pending secret
        $verifyResult = $this->totpService->verifyPendingCode($pendingSecret, (string) $request->input('code'));
        if (! $verifyResult['success']) {
            return back()->withErrors([
                'code' => 'The provided security code is invalid. Check the authenticator time and re-enter.',
            ]);
        }

        // Atomically activate credential, consume authorization, generate recovery codes
        DB::transaction(function () use ($user, $pendingSecret, $verifyResult, $authorization) {
            // Activate encrypted TOTP secret and record consumed confirmation step
            $user->setEncryptedTotpSecret($pendingSecret);
            $user->totp_last_step = $verifyResult['matched_step'];
            $user->mfa_enrolled_at = now();
            $user->save();

            // Consume authorization token if one was used
            if ($authorization) {
                $this->enrollmentAuthService->consumeAuthorization($authorization);
            }

            // Recovery codes are NOT generated at enrolment; users opt in later (2fa.recovery.manage).
        });

        AuditLog::create([
            'user_id' => $user->id,
            'action' => '2fa_enrolled',
            'table_name' => 'users',
            'record_id' => $user->id,
            'new_values' => ['method' => 'totp', 'authorized_by' => $authorization ? $authorization->authorized_by : $user->id],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Invalidate enrollment session state and rotate session ID
        $request->session()->forget([
            'mfa_enrollment_token',
            'mfa_enrollment_user_id',
            'mfa_enrollment_auth_id',
            'pending_totp_secret',
            'mfa_pending_user_id',
            'mfa_challenge_id',
            'mfa_status',
        ]);
        $request->session()->regenerate();

        // Establish fully authenticated session
        Auth::guard('web')->login($user, false);
        $request->session()->put([
            'auth_method' => 'totp',
            'mfa_completed_at' => now()->timestamp,
            'security_version' => (int) $user->security_version,
            'last_activity_at' => now()->timestamp,
            'session_created_at' => now()->timestamp,
            'sensitive_reauth_at' => now()->timestamp,
        ]);

        return redirect()->route('2fa.enroll.success');
    }

    /**
     * Get WebAuthn options for enrollment ceremony.
     */
    public function getWebAuthnEnrollmentOptions(Request $request): JsonResponse
    {
        $token = (string) $request->session()->get('mfa_enrollment_token');
        $user = null;
        if (!empty($token)) {
            $authorization = $this->enrollmentAuthService->findValidAuthorization($token);
            if (! $authorization) {
                return response()->json(['error' => 'INVALID_AUTHORIZATION'], 403);
            }
            $user = $authorization->user;
        } else {
            $userId = (int) ($request->session()->get('mfa_enrollment_user_id') ?: $request->session()->get('mfa_pending_user_id'));
            if ($userId > 0) {
                $user = User::find($userId);
            }
        }

        if (! $user) {
            return response()->json(['error' => 'UNAUTHENTICATED'], 401);
        }

        $args = $this->webAuthnService->generateRegistrationArgs($user, $user->isPrivileged());

        $request->session()->put('webauthn_reg_challenge', $args['challenge']);

        return response()->json($args['options']);
    }

    /**
     * Confirm WebAuthn enrollment.
     */
    public function confirmWebAuthn(Request $request): JsonResponse
    {
        $request->validate([
            'clientDataJSON' => ['required', 'string'],
            'attestationObject' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:50'],
        ]);

        $token = (string) $request->session()->get('mfa_enrollment_token');
        $user = null;
        $authorization = null;

        if (!empty($token)) {
            $authorization = $this->enrollmentAuthService->findValidAuthorization($token);
            if (! $authorization) {
                return response()->json(['error' => 'INVALID_AUTHORIZATION'], 403);
            }
            $user = $authorization->user;
        } else {
            $userId = (int) ($request->session()->get('mfa_enrollment_user_id') ?: $request->session()->get('mfa_pending_user_id'));
            if ($userId > 0) {
                $user = User::find($userId);
            }
        }

        if (! $user) {
            return response()->json(['error' => 'UNAUTHENTICATED'], 401);
        }

        $challenge = (string) $request->session()->get('webauthn_reg_challenge');
        if (empty($challenge)) {
            return response()->json(['error' => 'MISSING_CHALLENGE'], 400);
        }

        $deviceName = (string) ($request->input('device_name') ?: 'Hardware Security Key');

        $result = $this->webAuthnService->processRegistration(
            $user,
            (string) $request->input('clientDataJSON'),
            (string) $request->input('attestationObject'),
            $challenge,
            $deviceName
        );

        if (! $result['success']) {
            return response()->json(['error' => $result['error'] ?? 'Registration failed'], 422);
        }

        // Consume authorization if present and generate recovery codes
        DB::transaction(function () use ($user, $authorization) {
            $user->mfa_enrolled_at = now();
            $user->save();

            if ($authorization) {
                $this->enrollmentAuthService->consumeAuthorization($authorization);
            }
        });

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'webauthn_enrolled',
            'table_name' => 'webauthn_credentials',
            'record_id' => $result['credential']->id,
            'new_values' => ['device' => $deviceName],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $request->session()->forget([
            'mfa_enrollment_token',
            'mfa_enrollment_user_id',
            'mfa_enrollment_auth_id',
            'webauthn_reg_challenge',
            'mfa_pending_user_id',
            'mfa_challenge_id',
        ]);
        $request->session()->regenerate();

        Auth::guard('web')->login($user, false);
        $request->session()->put([
            'auth_method' => 'webauthn',
            'mfa_completed_at' => now()->timestamp,
            'security_version' => (int) $user->security_version,
            'last_activity_at' => now()->timestamp,
            'session_created_at' => now()->timestamp,
            'sensitive_reauth_at' => now()->timestamp,
        ]);

        return response()->json([
            'success' => true,
            'redirect' => route('2fa.enroll.success'),
        ]);
    }

    /**
     * Legacy endpoint: recovery codes are no longer issued or displayed.
     * Drops any stale session payload and sends the user on.
     */
    public function showSuccess(Request $request): RedirectResponse
    {
        return $this->acknowledgeSuccess($request);
    }

    public function acknowledgeSuccess(Request $request): RedirectResponse
    {
        $request->session()->forget('mfa_new_recovery_codes');

        /** @var User|null $authUser */
        $authUser = Auth::user();
        return redirect()->to($authUser ? $authUser->getLandingPageRoute() : route('dashboard'));
    }

    /**
     * Show authenticator replacement screen (for planned device changes or recovery-restricted sessions).
     */
    public function showReplace(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();

        $newSecret = $request->session()->get('replace_pending_secret');
        if (empty($newSecret)) {
            $newSecret = $this->totpService->generateSecret();
            $request->session()->put('replace_pending_secret', $newSecret);
        }

        $qrCodeDataUri = $this->totpService->getLocalQrCodeDataUri($user->username, $newSecret);

        return view('auth.two-factor-replace', [
            'user' => $user,
            'secret' => $newSecret,
            'qrCodeDataUri' => $qrCodeDataUri,
            'isRecoveryRestricted' => $request->session()->get('recovery_restricted', false),
        ]);
    }

    /**
     * Confirm authenticator replacement.
     */
    public function confirmReplace(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Current password is incorrect.']);
        }

        $newSecret = (string) $request->session()->get('replace_pending_secret');
        if (empty($newSecret)) {
            return redirect()->route('2fa.replace.show')->withErrors(['code' => 'Replacement session expired.']);
        }

        $verifyResult = $this->totpService->verifyPendingCode($newSecret, (string) $request->input('code'));
        if (! $verifyResult['success']) {
            return back()->withErrors(['code' => 'Verification code is invalid. Check your authenticator app.']);
        }

        // Atomically replace factor, revoke old credentials, generate new recovery codes, bump security version
        DB::transaction(function () use ($user, $newSecret, $verifyResult) {
            $user->setEncryptedTotpSecret($newSecret);
            $user->totp_last_step = $verifyResult['matched_step'];
            $user->security_version = ($user->security_version ?? 1) + 1;
            $user->save();

            // Old recovery codes are revoked; new ones are issued only on request.
            \App\Models\TwoFactorRecoveryCode::where('user_id', $user->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        });

        AuditLog::create([
            'user_id' => $user->id,
            'action' => '2fa_replaced',
            'table_name' => 'users',
            'record_id' => $user->id,
            'new_values' => ['action' => 'Authenticator replaced, security version incremented'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $request->session()->forget('replace_pending_secret');
        $request->session()->put([
            'recovery_restricted' => false, // Normal access restored!
            'security_version' => (int) $user->security_version,
        ]);

        return redirect()->route('2fa.enroll.success');
    }
}
