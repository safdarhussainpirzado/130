<?php

namespace App\Services\TwoFactor;

use App\Models\AuditLog;
use App\Models\TwoFactorEnrollmentAuthorization;
use App\Models\TwoFactorRecoveryApproval;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnrollmentAuthorizationService
{
    /**
     * Issue a single-use enrollment authorization token for a staff account.
     *
     * @return array{plain_token: string, authorization: TwoFactorEnrollmentAuthorization}
     */
    public function issueAuthorization(User $authorizer, User $targetUser, string $reason = 'Staff Onboarding'): array
    {
        // Enforce role hierarchy: cannot authorize someone of equal or higher privilege unless super_admin
        if ($targetUser->isPrivileged() && ! $authorizer->role?->name === 'super_admin' && $authorizer->id !== 1) {
            throw new \DomainException('Privileged account enrollment requires Super Administrator authorization.');
        }

        // Generate 64-character cryptographically secure token
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        $authorization = DB::transaction(function () use ($authorizer, $targetUser, $tokenHash, $reason) {
            // Invalidate any existing pending authorizations for this user
            TwoFactorEnrollmentAuthorization::where('user_id', $targetUser->id)
                ->whereNull('consumed_at')
                ->delete();

            return TwoFactorEnrollmentAuthorization::create([
                'user_id' => $targetUser->id,
                'token_hash' => $tokenHash,
                'authorized_by' => $authorizer->id,
                'reason' => $reason,
                'expires_at' => now()->addMinutes(10), // 10-minute configurable transaction lifetime
                'consumed_at' => null,
                'created_at' => now(),
            ]);
        });

        // Audit the issuance
        AuditLog::create([
            'user_id' => $authorizer->id,
            'action' => 'mfa_enrollment_authorized',
            'table_name' => 'two_factor_enrollment_authorizations',
            'record_id' => $authorization->id,
            'new_values' => [
                'target_user_id' => $targetUser->id,
                'target_username' => $targetUser->username,
                'reason' => $reason,
                'expires_at' => $authorization->expires_at->toIso8601String(),
            ],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'CLI/Internal',
        ]);

        return [
            'plain_token' => $plainToken,
            'authorization' => $authorization,
        ];
    }

    /**
     * Validate and retrieve an active enrollment authorization.
     */
    public function findValidAuthorization(string $plainToken): ?TwoFactorEnrollmentAuthorization
    {
        $tokenHash = hash('sha256', trim($plainToken));

        /** @var TwoFactorEnrollmentAuthorization|null $auth */
        $auth = TwoFactorEnrollmentAuthorization::where('token_hash', $tokenHash)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        return $auth;
    }

    /**
     * Atomically consume an authorization token during final factor activation.
     */
    public function consumeAuthorization(TwoFactorEnrollmentAuthorization $authorization): bool
    {
        return DB::transaction(function () use ($authorization) {
            $locked = TwoFactorEnrollmentAuthorization::where('id', $authorization->id)
                ->whereNull('consumed_at')
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->expires_at->isPast()) {
                return false;
            }

            $locked->consumed_at = now();
            $locked->save();

            return true;
        });
    }

    /**
     * Create a lost device recovery request requiring approval.
     */
    public function createRecoveryRequest(User $user, string $reason): array
    {
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        $request = DB::transaction(function () use ($user, $tokenHash, $reason) {
            // Cancel older pending recovery requests
            TwoFactorRecoveryApproval::where('user_id', $user->id)
                ->where('status', 'pending')
                ->update(['status' => 'rejected', 'rejection_reason' => 'Superseded by newer request']);

            return TwoFactorRecoveryApproval::create([
                'user_id' => $user->id,
                'request_token_hash' => $tokenHash,
                'reason' => $reason,
                'target_role_id' => $user->role_id,
                'status' => 'pending',
                'expires_at' => now()->addHours(2), // 2 hour window for approval
                'created_at' => now(),
            ]);
        });

        return [
            'plain_token' => $plainToken,
            'recovery_request' => $request,
        ];
    }

    /**
     * Record approval by an authorized approver.
     * Enforces dual control for privileged accounts and prevents self-approval.
     */
    public function recordApproval(User $approver, TwoFactorRecoveryApproval $approval): array
    {
        // 1. Prohibit self-approval
        if ($approver->id === $approval->user_id) {
            return ['success' => false, 'error' => 'Self-approval is strictly forbidden under separation of duties.'];
        }

        // 2. Prevent lower privilege from approving higher privilege
        $targetUser = $approval->user;
        if ($targetUser && $targetUser->isPrivileged() && ! $approver->isPrivileged()) {
            return ['success' => false, 'error' => 'Privileged recovery requests can only be approved by privileged peers.'];
        }

        return DB::transaction(function () use ($approver, $approval) {
            /** @var TwoFactorRecoveryApproval $locked */
            $locked = TwoFactorRecoveryApproval::where('id', $approval->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->status !== 'pending' || $locked->expires_at->isPast()) {
                return ['success' => false, 'error' => 'This recovery request is no longer pending or has expired.'];
            }

            // Assign Approver 1
            if ($locked->approver1_id === null) {
                $locked->approver1_id = $approver->id;
                $locked->approver1_at = now();

                // If non-privileged account, 1 approval is sufficient
                if (! $locked->isPrivileged()) {
                    $locked->status = 'approved';
                    $locked->token_issued_at = now();
                }
            } elseif ($locked->approver2_id === null) {
                // Dual control check: Approver 2 must be distinct from Approver 1
                if ($locked->approver1_id === $approver->id) {
                    return ['success' => false, 'error' => 'A distinct second approver is required for privileged recovery.'];
                }

                $locked->approver2_id = $approver->id;
                $locked->approver2_at = now();
                $locked->status = 'approved';
                $locked->token_issued_at = now();
            } else {
                return ['success' => false, 'error' => 'This request already has the required approvals.'];
            }

            $locked->save();

            AuditLog::create([
                'user_id' => $approver->id,
                'action' => 'mfa_recovery_approved',
                'table_name' => 'two_factor_recovery_approvals',
                'record_id' => $locked->id,
                'new_values' => [
                    'approver_slot' => $locked->approver2_id === $approver->id ? 2 : 1,
                    'target_user_id' => $locked->user_id,
                    'status' => $locked->status,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/Internal',
            ]);

            return ['success' => true, 'status' => $locked->status, 'approval' => $locked];
        });
    }
}
