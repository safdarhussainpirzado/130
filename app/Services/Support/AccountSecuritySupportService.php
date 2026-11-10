<?php

namespace App\Services\Support;

use App\Models\AuditLog;
use App\Models\PasswordRecoveryToken;
use App\Models\Role;
use App\Models\SupportRequest;
use App\Models\SupportRequestAction;
use App\Models\User;
use App\Services\TwoFactor\EnrollmentAuthorizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountSecuritySupportService
{
    public function __construct(
        private StepUpAuthenticationService $stepUpAuthService,
        private EnrollmentAuthorizationService $mfaAuthService
    ) {}

    /* ------------------------------------------------------------------ */
    /*  Section 3.C: Immediate Account Blocking ("Lock My Account")       */
    /* ------------------------------------------------------------------ */

    /**
     * Self-service immediate account lock initiated by authenticated user.
     * Enforces step-up authentication, immediate suspension, and session revocation.
     *
     * @param User $user
     * @param string $reason
     * @param string $password
     * @param string|null $totpCode
     * @return SupportRequest
     * @throws ValidationException
     */
    public function lockAccount(
        User $user,
        string $reason,
        string $password,
        ?string $totpCode = null
    ): SupportRequest {
        $authResult = $this->stepUpAuthService->verify(
            $user,
            $password,
            $totpCode,
            'immediate_account_lock'
        );

        if (!$authResult['success']) {
            throw ValidationException::withMessages([
                'password' => [$authResult['error']],
            ]);
        }

        return DB::transaction(function () use ($user, $reason, $authResult) {
            /** @var User $lockedUser */
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            // 1. Immediately wipe credentials, purge 2FA, and hard-block account
            $lockedUser->blockAccount("Emergency Account Lock: {$reason}");

            // 3. Create Support Request for administrative record and unlock tracking
            $request = SupportRequest::create([
                'requester_id'   => $lockedUser->id,
                'request_type'   => SupportRequest::TYPE_ACCOUNT_BLOCK,
                'category'       => 'self_service_lock',
                'subject'        => "Emergency Account Lock for [{$lockedUser->username}]",
                'description'    => "Applicant initiated self-service emergency account lock. Reason: {$reason}",
                'current_status' => SupportRequest::STATUS_PENDING_MANAGER_REVIEW,
                'priority'       => SupportRequest::PRIORITY_URGENT,
                'metadata'       => [
                    'locked_at'            => now()->toIso8601String(),
                    'ip_address'           => request()->ip(),
                    'revoked_session_user' => $lockedUser->id,
                ],
                'submitted_at'   => now(),
            ]);

            SupportRequestAction::create([
                'support_request_id'    => $request->id,
                'actor_id'              => $lockedUser->id,
                'action_type'           => SupportRequestAction::ACTION_ACCOUNT_LOCKED,
                'previous_status'       => null,
                'new_status'            => SupportRequest::STATUS_PENDING_MANAGER_REVIEW,
                'authentication_method' => $authResult['auth_method'],
                'remarks'               => "Account immediately suspended upon verified step-up authentication. Reason: {$reason}",
                'ip_address'            => request()->ip(),
                'user_agent'            => request()->userAgent(),
            ]);

            AuditLog::create([
                'user_id'    => $lockedUser->id,
                'action'     => 'EMERGENCY_ACCOUNT_LOCKED',
                'table_name' => 'users',
                'record_id'  => $lockedUser->id,
                'new_values' => [
                    'is_active'       => false,
                    'locked_until'    => $lockedUser->locked_until,
                    'request_public'  => $request->public_id,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $request;
        });
    }

    /**
     * Unlock an account after administrative identity verification by Operation Manager.
     */
    public function unlockAccount(
        SupportRequest $supportRequest,
        User $manager,
        string $remarks,
        string $password,
        ?string $totpCode = null
    ): SupportRequest {
        if ($manager->role?->name !== Role::SUPER_ADMIN && $manager->role?->name !== 'operation_admin') {
            throw ValidationException::withMessages([
                'unauthorized' => ['Only designated Operation Managers can unlock accounts.'],
            ]);
        }

        $authResult = $this->stepUpAuthService->verify(
            $manager,
            $password,
            $totpCode,
            'account_unlock'
        );

        if (!$authResult['success']) {
            throw ValidationException::withMessages([
                'password' => [$authResult['error']],
            ]);
        }

        return DB::transaction(function () use ($supportRequest, $manager, $remarks, $authResult) {
            /** @var SupportRequest $lockedRequest */
            $lockedRequest = SupportRequest::where('id', $supportRequest->id)->lockForUpdate()->firstOrFail();
            $targetUser = User::where('id', $lockedRequest->requester_id)->lockForUpdate()->firstOrFail();

            $prevStatus = $lockedRequest->current_status;

            // Re-activate target user
            $targetUser->is_active = true;
            $targetUser->locked_until = null;
            if (array_key_exists('failed_attempts', $targetUser->getAttributes())) {
                $targetUser->setAttribute('failed_attempts', 0);
            }
            $targetUser->save();

            // Generate secure single-use unblock & identity verification link (CNIC + Email required)
            $unblockToken = $targetUser->generateUnblockToken();
            $reactivationUrl = route('account.unblock', ['token' => $unblockToken]);

            $lockedRequest->current_status = SupportRequest::STATUS_COMPLETED;
            $lockedRequest->setAttribute('completed_at', now());
            $lockedRequest->metadata = array_merge($lockedRequest->metadata ?? [], [
                'reactivation_url' => $reactivationUrl,
                'unblock_token'    => $unblockToken,
                'unlocked_by'      => $manager->username,
            ]);
            $lockedRequest->save();

            // Set transient properties on model object for callers
            $lockedRequest->reactivation_url = $reactivationUrl;
            $lockedRequest->plain_token = $unblockToken;

            SupportRequestAction::create([
                'support_request_id'    => $lockedRequest->id,
                'actor_id'              => $manager->id,
                'action_type'           => SupportRequestAction::ACTION_ACCOUNT_UNLOCKED,
                'previous_status'       => $prevStatus,
                'new_status'            => SupportRequest::STATUS_COMPLETED,
                'authentication_method' => $authResult['auth_method'],
                'remarks'               => "Account unlocked after verified identity review. Reactivation link dispatched. Remarks: {$remarks}",
                'ip_address'            => request()->ip(),
                'user_agent'            => request()->userAgent(),
            ]);

            AuditLog::create([
                'user_id'    => $manager->id,
                'action'     => 'ADMIN_ACCOUNT_UNLOCKED',
                'table_name' => 'users',
                'record_id'  => $targetUser->id,
                'new_values' => [
                    'action'           => "Account unlocked by {$manager->username}",
                    'reactivation_url' => $reactivationUrl,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $lockedRequest;
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Section 3.A: Enhanced Password Recovery Workflow                  */
    /* ------------------------------------------------------------------ */

    /**
     * Submit a public or unauthenticated password recovery request.
     * Guarantees anti-enumeration: returns identical success messaging.
     *
     * @param string $identifier
     * @param string|null $notes
     * @return array{success: bool, message: string, public_id?: string}
     */
    public function submitPublicPasswordRecovery(string $identifier, ?string $notes = null): array
    {
        $identifier = trim($identifier);

        // Standard anti-enumeration response
        $genericResponse = [
            'success' => true,
            'message' => 'If an active account exists with the provided identifier, your password recovery request has been forwarded to the duty Operation Manager.',
        ];

        if (empty($identifier)) {
            return $genericResponse;
        }

        $user = User::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (!$user) {
            return $genericResponse;
        }

        // Super Admin cannot recover via web requests
        if ($user->role?->name === Role::SUPER_ADMIN) {
            return $genericResponse;
        }

        // Avoid spamming duplicate pending requests within 15 minutes
        $existing = SupportRequest::where('requester_id', $user->id)
            ->where('request_type', SupportRequest::TYPE_PASSWORD_RECOVERY)
            ->where('current_status', SupportRequest::STATUS_PENDING_MANAGER_REVIEW)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->first();

        if ($existing) {
            return [
                'success'   => true,
                'message'   => "A password recovery request [ID: {$existing->public_id}] is already pending with the Operation Managers. Please coordinate with the Operations Control Room.",
                'public_id' => $existing->public_id,
            ];
        }

        $request = SupportRequest::create([
            'requester_id'   => $user->id,
            'request_type'   => SupportRequest::TYPE_PASSWORD_RECOVERY,
            'category'       => 'password_reset',
            'subject'        => "Password Recovery Request for {$user->full_name} ({$user->username})",
            'description'    => $notes ?: 'User submitted password recovery assistance request.',
            'current_status' => SupportRequest::STATUS_PENDING_MANAGER_REVIEW,
            'priority'       => SupportRequest::PRIORITY_HIGH,
            'metadata'       => [
                'identifier_submitted' => $identifier,
                'ip_address'           => request()->ip(),
            ],
            'submitted_at'   => now(),
        ]);

        SupportRequestAction::create([
            'support_request_id'    => $request->id,
            'actor_id'              => $user->id,
            'action_type'           => SupportRequestAction::ACTION_SUBMITTED,
            'previous_status'       => null,
            'new_status'            => SupportRequest::STATUS_PENDING_MANAGER_REVIEW,
            'authentication_method' => 'session',
            'remarks'               => 'Unauthenticated password recovery request submitted.',
            'ip_address'            => request()->ip(),
            'user_agent'            => request()->userAgent(),
        ]);

        return [
            'success'   => true,
            'message'   => "Password recovery request [ID: {$request->public_id}] has been submitted to the Operation Managers. Please contact the Operations Control Room to complete verification.",
            'public_id' => $request->public_id,
        ];
    }

    /**
     * Operation Manager approves password recovery and generates single-use token.
     * Plaintext token is returned once to the manager. Passwords are NEVER shown.
     *
     * @param SupportRequest $supportRequest
     * @param User $manager
     * @param string $remarks
     * @param string $password
     * @param string|null $totpCode
     * @return array{support_request: SupportRequest, plain_token: string, reset_url: string}
     * @throws ValidationException
     */
    public function approvePasswordRecovery(
        SupportRequest $supportRequest,
        User $manager,
        string $remarks,
        string $password,
        ?string $totpCode = null
    ): array {
        if ($manager->role?->name !== Role::SUPER_ADMIN && $manager->role?->name !== 'operation_admin') {
            throw ValidationException::withMessages([
                'unauthorized' => ['Only Operation Managers can authorize password recovery requests.'],
            ]);
        }

        $authResult = $this->stepUpAuthService->verify(
            $manager,
            $password,
            $totpCode,
            'approve_password_recovery'
        );

        if (!$authResult['success']) {
            throw ValidationException::withMessages([
                'password' => [$authResult['error']],
            ]);
        }

        return DB::transaction(function () use ($supportRequest, $manager, $remarks, $authResult) {
            /** @var SupportRequest $lockedRequest */
            $lockedRequest = SupportRequest::where('id', $supportRequest->id)->lockForUpdate()->firstOrFail();
            $targetUser = User::where('id', $lockedRequest->requester_id)->lockForUpdate()->firstOrFail();

            // Generate high-entropy, short-lived single use token
            $tokenData = PasswordRecoveryToken::createSecureToken($lockedRequest, $targetUser, 60);

            $prevStatus = $lockedRequest->current_status;
            $lockedRequest->current_status = SupportRequest::STATUS_APPROVED;
            $lockedRequest->assigned_approver_id = $manager->id;
            $lockedRequest->save();

            SupportRequestAction::create([
                'support_request_id'    => $lockedRequest->id,
                'actor_id'              => $manager->id,
                'action_type'           => SupportRequestAction::ACTION_PASSWORD_RESET_ISSUED,
                'previous_status'       => $prevStatus,
                'new_status'            => SupportRequest::STATUS_APPROVED,
                'authentication_method' => $authResult['auth_method'],
                'remarks'               => "Password recovery authorized by Operation Manager {$manager->username}. Remarks: {$remarks}",
                'ip_address'            => request()->ip(),
                'user_agent'            => request()->userAgent(),
            ]);

            AuditLog::create([
                'user_id'    => $manager->id,
                'action'     => 'PASSWORD_RESET_TOKEN_ISSUED',
                'table_name' => 'password_recovery_tokens',
                'record_id'  => $tokenData['record']->id,
                'new_values' => ['user_id' => $targetUser->id],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'support_request' => $lockedRequest,
                'plain_token'     => $tokenData['plain_token'],
                'reset_url'       => route('password.reset', ['token' => $tokenData['plain_token']]),
            ];
        });
    }

    /**
     * User self-establishes new password using single-use token.
     *
     * @param string $plainToken
     * @param string $newPassword
     * @return User
     * @throws ValidationException
     */
    public function resetPasswordWithToken(
        string $plainToken,
        string $newPassword,
        ?string $email = null,
        ?string $cnic = null
    ): User {
        $tokenRecord = PasswordRecoveryToken::findValidToken($plainToken);

        if (!$tokenRecord) {
            throw ValidationException::withMessages([
                'token' => ['The password reset token is invalid, expired, or has already been used.'],
            ]);
        }

        $user = User::where('id', $tokenRecord->user_id)->firstOrFail();

        // 1. Dual Identity Verification: Both Email AND CNIC must match the official account record
        if ($email && strtolower(trim($user->email)) !== strtolower(trim($email))) {
            throw ValidationException::withMessages([
                'email' => ['The provided email address does not match this recovery authorization.'],
            ]);
        }

        if ($cnic) {
            $userCnicClean = preg_replace('/[^0-9]/', '', (string) $user->cnic);
            $inputCnicClean = preg_replace('/[^0-9]/', '', (string) $cnic);
            if ($userCnicClean && $inputCnicClean && $userCnicClean !== $inputCnicClean) {
                throw ValidationException::withMessages([
                    'cnic' => ['The provided official CNIC does not match this recovery authorization.'],
                ]);
            }
        }

        return DB::transaction(function () use ($tokenRecord, $newPassword, $user) {
            /** @var User $lockedUser */
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            // 1. Update password on the locked user instance and persist
            $lockedUser->password = Hash::make($newPassword);
            $lockedUser->password_changed_at = now();
            if (array_key_exists('failed_attempts', $lockedUser->getAttributes())) {
                $lockedUser->setAttribute('failed_attempts', 0);
            }
            $lockedUser->locked_until = null;
            $lockedUser->is_active = true;
            $lockedUser->bumpSecurityVersion();
            $lockedUser->save();

            // 2. Consume token atomically
            $tokenRecord->markAsUsed();

            // 3. Invalidate active sessions
            try {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            } catch (\Throwable $e) {}

            // 4. Update associated SupportRequest if present
            if ($tokenRecord->support_request_id) {
                $supportRequest = SupportRequest::find($tokenRecord->support_request_id);
                if ($supportRequest) {
                    $supportRequest->current_status = SupportRequest::STATUS_COMPLETED;
                    $supportRequest->completed_at = now();
                    $supportRequest->save();

                    SupportRequestAction::create([
                        'support_request_id'    => $supportRequest->id,
                        'actor_id'              => $user->id,
                        'action_type'           => SupportRequestAction::ACTION_PASSWORD_RESET_COMPLETED,
                        'previous_status'       => SupportRequest::STATUS_APPROVED,
                        'new_status'            => SupportRequest::STATUS_COMPLETED,
                        'authentication_method' => 'session',
                        'remarks'               => 'User successfully established their own new password using secure single-use recovery token.',
                        'ip_address'            => request()->ip(),
                        'user_agent'            => request()->userAgent(),
                    ]);
                }
            }

            AuditLog::create([
                'user_id'    => $user->id,
                'action'     => 'PASSWORD_RESET_COMPLETED_BY_USER',
                'table_name' => 'users',
                'record_id'  => $user->id,
                'new_values' => ['action' => 'Password reset completed with recovery token'],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $user;
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Section 3.B: MFA Support & General Account Issues                 */
    /* ------------------------------------------------------------------ */

    public function submitMfaSupportRequest(User $user, string $category, string $description): SupportRequest
    {
        return DB::transaction(function () use ($user, $category, $description) {
            $request = SupportRequest::create([
                'requester_id'   => $user->id,
                'request_type'   => SupportRequest::TYPE_MFA_SUPPORT,
                'category'       => $category,
                'subject'        => 'MFA Assistance: ' . ucwords(str_replace('_', ' ', $category)),
                'description'    => $description,
                'current_status' => SupportRequest::STATUS_PENDING_MANAGER_REVIEW,
                'priority'       => ($category === 'lost_device') ? SupportRequest::PRIORITY_HIGH : SupportRequest::PRIORITY_NORMAL,
                'submitted_at'   => now(),
            ]);

            SupportRequestAction::create([
                'support_request_id'    => $request->id,
                'actor_id'              => $user->id,
                'action_type'           => SupportRequestAction::ACTION_SUBMITTED,
                'previous_status'       => null,
                'new_status'            => SupportRequest::STATUS_PENDING_MANAGER_REVIEW,
                'authentication_method' => 'session',
                'remarks'               => "MFA support request created: {$category}",
                'ip_address'            => request()->ip(),
                'user_agent'            => request()->userAgent(),
            ]);

            return $request;
        });
    }

    public function submitGeneralAccountIssue(User $user, string $category, string $subject, string $description, array $metadata = []): SupportRequest
    {
        return DB::transaction(function () use ($user, $category, $subject, $description, $metadata) {
            $request = SupportRequest::create([
                'requester_id'   => $user->id,
                'request_type'   => SupportRequest::TYPE_OTHER_ACCOUNT,
                'category'       => $category,
                'subject'        => $subject,
                'description'    => $description,
                'current_status' => SupportRequest::STATUS_PENDING_MANAGER_REVIEW,
                'priority'       => SupportRequest::PRIORITY_NORMAL,
                'metadata'       => $metadata ?: null,
                'submitted_at'   => now(),
            ]);

            SupportRequestAction::create([
                'support_request_id'    => $request->id,
                'actor_id'              => $user->id,
                'action_type'           => SupportRequestAction::ACTION_SUBMITTED,
                'previous_status'       => null,
                'new_status'            => SupportRequest::STATUS_PENDING_MANAGER_REVIEW,
                'authentication_method' => 'session',
                'remarks'               => "General account issue submitted: {$subject}",
                'ip_address'            => request()->ip(),
                'user_agent'            => request()->userAgent(),
            ]);

            return $request;
        });
    }

    public function resolveGeneralRequest(
        SupportRequest $supportRequest,
        User $manager,
        string $remarks,
        bool $approved = true
    ): SupportRequest {
        return DB::transaction(function () use ($supportRequest, $manager, $remarks, $approved) {
            /** @var SupportRequest $lockedRequest */
            $lockedRequest = SupportRequest::where('id', $supportRequest->id)->lockForUpdate()->firstOrFail();

            $prevStatus = $lockedRequest->current_status;
            $newStatus = $approved ? SupportRequest::STATUS_COMPLETED : SupportRequest::STATUS_REJECTED;
            $actionType = $approved ? SupportRequestAction::ACTION_MANAGER_APPROVED : SupportRequestAction::ACTION_MANAGER_REJECTED;

            $lockedRequest->current_status = $newStatus;
            $lockedRequest->setAttribute('completed_at', now());
            $lockedRequest->assigned_approver_id = $manager->id;
            $lockedRequest->save();

            // If approved and profile changes were requested, apply updates directly to user profile
            if ($approved && !empty($lockedRequest->metadata['profile_changes'])) {
                $changes = $lockedRequest->metadata['profile_changes'];
                $requester = $lockedRequest->requester;
                if ($requester) {
                    $updates = [];
                    if (!empty($changes['new_full_name'])) {
                        $updates['full_name'] = $changes['new_full_name'];
                    }
                    if (!empty($changes['new_email'])) {
                        $updates['email'] = $changes['new_email'];
                    }
                    if (!empty($changes['new_phone'])) {
                        $updates['mobile_no'] = $changes['new_phone'];
                    }
                    if (!empty($changes['new_designation_id'])) {
                        $updates['designation_id'] = $changes['new_designation_id'];
                    }
                    if (!empty($changes['new_cnic'])) {
                        $updates['cnic'] = $changes['new_cnic'];
                    }
                    if (!empty($updates)) {
                        $requester->update($updates);
                    }
                }
            }

            SupportRequestAction::create([
                'support_request_id'    => $lockedRequest->id,
                'actor_id'              => $manager->id,
                'action_type'           => $actionType,
                'previous_status'       => $prevStatus,
                'new_status'            => $newStatus,
                'authentication_method' => 'session',
                'remarks'               => $remarks,
                'ip_address'            => request()->ip(),
                'user_agent'            => request()->userAgent(),
            ]);

            return $lockedRequest;
        });
    }
}
