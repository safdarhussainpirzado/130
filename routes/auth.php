<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordExpiredController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\ReauthenticationController;
use App\Http\Controllers\Auth\SessionManagementController;
use App\Http\Controllers\Auth\TwoFactorAuthenticatedController;
use App\Http\Controllers\Auth\TwoFactorEnrollmentController;
use App\Http\Controllers\Auth\TwoFactorRecoveryAdminController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

// NOTE: Public self-registration is DISABLED.
$authThrottle = env('AUTH_THROTTLE_LIMIT', '10000,1');

Route::middleware('guest')->group(function () use ($authThrottle) {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware("throttle:{$authThrottle}")
        ->name('login.store');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');

    Route::get('account/unblock', [\App\Http\Controllers\Auth\AccountUnblockController::class, 'show'])
        ->name('account.unblock');

    Route::post('account/unblock/verify', [\App\Http\Controllers\Auth\AccountUnblockController::class, 'verify'])
        ->middleware('throttle:6,1')
        ->name('account.unblock.verify');
});

// ── Multi-Factor Authentication: Pre-Authentication State Machine Routes ──
Route::prefix('2fa')->group(function () use ($authThrottle) {
    // State 2: MFA Challenge routes
    Route::get('challenge', [TwoFactorAuthenticatedController::class, 'showChallenge'])->name('2fa.challenge');
    Route::post('challenge', [TwoFactorAuthenticatedController::class, 'verifyTotp'])
        ->middleware("throttle:{$authThrottle}")
        ->name('2fa.challenge.totp');

    // WebAuthn Assertion challenge
    Route::get('challenge/webauthn/options', [TwoFactorAuthenticatedController::class, 'getWebAuthnOptions'])->name('2fa.challenge.webauthn.options');
    Route::post('challenge/webauthn', [TwoFactorAuthenticatedController::class, 'verifyWebAuthn'])
        ->middleware('throttle:30,1')
        ->name('2fa.challenge.webauthn.verify');

    // State 3: Controlled First-Time Enrollment
    Route::get('enroll-required', [TwoFactorEnrollmentController::class, 'showEnrollmentRequired'])->name('2fa.enroll.required');
    Route::get('enroll', [TwoFactorEnrollmentController::class, 'showEnroll'])->name('2fa.enroll.show');
    Route::post('enroll/confirm', [TwoFactorEnrollmentController::class, 'confirmTotp'])
        ->middleware('throttle:30,1')
        ->name('2fa.enroll.confirm');
    Route::get('enroll/webauthn/options', [TwoFactorEnrollmentController::class, 'getWebAuthnEnrollmentOptions'])->name('2fa.enroll.webauthn.options');
    Route::post('enroll/webauthn', [TwoFactorEnrollmentController::class, 'confirmWebAuthn'])
        ->middleware('throttle:30,1')
        ->name('2fa.enroll.webauthn.register');
    Route::get('enroll/success', [TwoFactorEnrollmentController::class, 'showSuccess'])->name('2fa.enroll.success');
    Route::post('enroll/acknowledge', [TwoFactorEnrollmentController::class, 'acknowledgeSuccess'])->name('2fa.enroll.acknowledge');

    // Legacy Route Aliases
    Route::get('/', [TwoFactorAuthenticatedController::class, 'showChallenge'])->name('2fa.index');
    Route::post('/', [TwoFactorAuthenticatedController::class, 'verifyTotp'])->name('2fa.verify');
    Route::get('setup', [TwoFactorEnrollmentController::class, 'showEnrollmentRequired'])->name('2fa.setup');
});

// ── Authenticated Routes (Requires Fully Authenticated State) ──
Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');


    // 15-Day Mandatory Password Expiration Enforcement
    Route::get('password/expired', [PasswordExpiredController::class, 'show'])->name('password.expired');
    Route::post('password/expired', [PasswordExpiredController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('password.expired.update');

    // Authenticator Replacement (Planned or Restricted Recovery Mode)
    Route::get('2fa/replace', [TwoFactorEnrollmentController::class, 'showReplace'])->name('2fa.replace.show');
    Route::post('2fa/replace', [TwoFactorEnrollmentController::class, 'confirmReplace'])
        ->middleware('throttle:5,1')
        ->name('2fa.replace.confirm');

    // Step-up Re-authentication
    Route::get('auth/reauthenticate', [ReauthenticationController::class, 'show'])->name('auth.reauthenticate.show');
    Route::post('auth/reauthenticate', [ReauthenticationController::class, 'verify'])
        ->middleware('throttle:5,1')
        ->name('auth.reauthenticate.verify');

    // Active Session Management
    Route::get('account/sessions', [SessionManagementController::class, 'index'])->name('account.sessions.index');
    Route::delete('account/sessions/{maskedId}', [SessionManagementController::class, 'revoke'])->name('account.sessions.revoke');
    Route::post('account/sessions/logout-other-devices', [SessionManagementController::class, 'logoutOtherDevices'])->name('account.sessions.logout-other');

    // Admin MFA Recovery & Authorization Management
    Route::prefix('mgmt/mfa')->name('admin.mfa.')->group(function () {
        Route::get('recovery', [TwoFactorRecoveryAdminController::class, 'index'])
            ->middleware('can:users.view')
            ->name('recovery.index');

        Route::post('recovery/{approval}/approve', [TwoFactorRecoveryAdminController::class, 'approve'])
            ->middleware(['can:users.manage_status', 'mfa.fresh:mfa_recovery_approval'])
            ->name('recovery.approve');

        Route::post('recovery/{approval}/reject', [TwoFactorRecoveryAdminController::class, 'reject'])
            ->middleware(['can:users.manage_status', 'mfa.fresh:mfa_recovery_rejection'])
            ->name('recovery.reject');

        Route::post('users/{user}/issue-token', [TwoFactorRecoveryAdminController::class, 'issueEnrollmentToken'])
            ->middleware(['can:users.manage_password', 'mfa.fresh:mfa_enrollment_issuance'])
            ->name('issue-token');
    });

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
    Route::get('logout', [AuthenticatedSessionController::class, 'destroy']);
});
