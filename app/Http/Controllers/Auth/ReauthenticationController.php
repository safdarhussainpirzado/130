<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TwoFactor\TotpService;
use App\Services\TwoFactor\WebAuthnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ReauthenticationController extends Controller
{
    public function __construct(
        private TotpService $totpService,
        private WebAuthnService $webAuthnService
    ) {}

    /**
     * Show the sensitive action step-up re-authentication screen.
     */
    public function show(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        $purpose = $request->session()->get('reauth_purpose', 'sensitive_action');

        return view('auth.reauthenticate', [
            'user'             => $user,
            'purpose'          => $purpose,
            'requiresWebAuthn' => $user->requiresWebAuthn(),
            'hasWebAuthn'      => $user->activeWebAuthnCredentials()->exists(),
        ]);
    }

    /**
     * Verify credentials for step-up freshness.
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
            'code'     => ['nullable', 'string'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        // 1. Password verification
        if (!Hash::check((string) $request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Password is incorrect.']);
        }

        // 2. MFA verification
        $code = (string) $request->input('code');
        if (!empty($code)) {
            $result = $this->totpService->verifyCode($user->id, $code);
            if (!$result['success']) {
                AuditLog::create([
                    'user_id'    => $user->id,
                    'action'     => 'reauth_failed',
                    'table_name' => 'users',
                    'record_id'  => $user->id,
                    'new_values' => ['reason' => $result['reason']],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return back()->withErrors(['code' => 'The provided MFA code is invalid or has already been used.']);
            }
        } elseif ($user->requiresWebAuthn()) {
            return back()->withErrors(['code' => 'Privileged step-up actions require fresh hardware security key verification.']);
        }

        // 3. Set fresh re-authentication timestamp bound to purpose
        $purpose = $request->session()->pull('reauth_purpose', 'sensitive_action');
        $intendedUrl = $request->session()->pull('reauth_intended_url');

        $request->session()->put([
            'sensitive_reauth_at'      => now()->timestamp,
            'sensitive_reauth_purpose' => $purpose,
        ]);

        AuditLog::create([
            'user_id'    => $user->id,
            'action'     => 'reauth_success',
            'table_name' => 'users',
            'record_id'  => $user->id,
            'new_values' => ['purpose' => $purpose],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($intendedUrl && str_starts_with($intendedUrl, config('app.url'))) {
            return redirect($intendedUrl);
        }

        return redirect()->to($user->getLandingPageRoute());
    }
}
