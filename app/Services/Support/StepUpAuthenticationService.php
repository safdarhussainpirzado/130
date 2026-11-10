<?php

namespace App\Services\Support;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\TwoFactor\TotpService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class StepUpAuthenticationService
{
    public function __construct(
        private TotpService $totpService
    ) {}

    /**
     * Verify step-up credentials for a sensitive operation.
     * Enforces:
     * - Rate limiting (max 5 attempts per minute per user/IP).
     * - Password check against current user password.
     * - Monotonic TOTP check with replay prevention if user has MFA enrolled.
     * - Returns auth_method ('password_totp' or 'password') on success.
     *
     * @param User $user
     * @param string $password
     * @param string|null $totpCode
     * @param string $actionContext
     * @return array{success: bool, auth_method: string, error?: string}
     */
    public function verify(
        User $user,
        string $password,
        ?string $totpCode = null,
        string $actionContext = 'sensitive_action'
    ): array {
        // 0. Administrative Privilege: Super Admin and Operation Admin do not require
        // password/2FA re-prompt when performing supervisory approvals or management actions.
        $roleName = $user->role?->name;
        $isAdminRole = in_array($roleName, ['super_admin', 'operation_admin'], true);

        if ($isAdminRole && in_array($actionContext, [
            'transfer_approval',
            'transfer_source_verification',
            'transfer_destination_confirmation',
            'account_unblock',
            'account_unlock',
            'account_block',
            'mfa_resolution',
            'password_reset_approval',
            'approve_password_recovery',
        ], true)) {
            $this->logAudit($user, 'step_up_admin_session_authorized', [
                'context' => $actionContext,
                'role'    => $roleName,
            ]);

            return [
                'success'     => true,
                'auth_method' => 'admin_session_override',
            ];
        }

        $rateLimitKey = 'step_up_auth:' . $user->id . ':' . (request()->ip() ?? '127.0.0.1');

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return [
                'success'     => false,
                'auth_method' => 'none',
                'error'       => "Too many authentication attempts. Please try again in {$seconds} seconds.",
            ];
        }

        // 1. Password Verification
        if (!Hash::check($password, $user->password)) {
            RateLimiter::hit($rateLimitKey, 60);

            $this->logAudit($user, 'step_up_password_failed', [
                'context' => $actionContext,
                'reason'  => 'INVALID_PASSWORD',
            ]);

            return [
                'success'     => false,
                'auth_method' => 'password',
                'error'       => 'Current password is incorrect.',
            ];
        }

        // 2. Multi-Factor Verification (if MFA is enrolled)
        if ($user->hasMfaEnrolled()) {
            if (empty($totpCode)) {
                return [
                    'success'     => false,
                    'auth_method' => 'password_totp',
                    'error'       => 'Authenticator TOTP verification code is required for your account.',
                ];
            }

            $totpResult = $this->totpService->verifyCode($user->id, $totpCode);
            if (!$totpResult['success']) {
                RateLimiter::hit($rateLimitKey, 60);

                $this->logAudit($user, 'step_up_totp_failed', [
                    'context' => $actionContext,
                    'reason'  => $totpResult['reason'],
                ]);

                $errorMessage = match ($totpResult['reason']) {
                    'CODE_REPLAY_DETECTED' => 'This authenticator code has already been used. Please wait for the next 30-second token.',
                    'NO_SECRET_CONFIGURED' => 'No active MFA secret configured for your account.',
                    default                => 'Invalid or expired authenticator code.',
                };

                return [
                    'success'     => false,
                    'auth_method' => 'password_totp',
                    'error'       => $errorMessage,
                ];
            }

            RateLimiter::clear($rateLimitKey);

            $this->logAudit($user, 'step_up_success', [
                'context'     => $actionContext,
                'auth_method' => 'password_totp',
            ]);

            return [
                'success'     => true,
                'auth_method' => 'password_totp',
            ];
        }

        // Standard Password-Only Step-up
        RateLimiter::clear($rateLimitKey);

        $this->logAudit($user, 'step_up_success', [
            'context'     => $actionContext,
            'auth_method' => 'password',
        ]);

        return [
            'success'     => true,
            'auth_method' => 'password',
        ];
    }

    private function logAudit(User $user, string $action, array $details): void
    {
        try {
            AuditLog::create([
                'user_id'    => $user->id,
                'action'     => $action,
                'table_name' => 'users',
                'record_id'  => $user->id,
                'new_values' => $details,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Non-blocking audit logging
        }
    }
}
