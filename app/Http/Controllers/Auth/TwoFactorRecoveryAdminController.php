<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TwoFactorRecoveryApproval;
use App\Models\User;
use App\Services\TwoFactor\EnrollmentAuthorizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TwoFactorRecoveryAdminController extends Controller
{
    public function __construct(
        private EnrollmentAuthorizationService $enrollmentAuthService
    ) {}

    /**
     * Display list of MFA lost-device recovery requests and active authorizations.
     */
    public function index(Request $request): View
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        $pendingApprovals = TwoFactorRecoveryApproval::with(['user.role', 'approver1', 'approver2'])
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $recentApprovals = TwoFactorRecoveryApproval::with(['user.role', 'approver1', 'approver2'])
            ->whereIn('status', ['approved', 'rejected'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return view('admin.mfa.recovery-approvals', [
            'pendingApprovals' => $pendingApprovals,
            'recentApprovals' => $recentApprovals,
            'currentUser' => $currentUser,
        ]);
    }

    /**
     * Record approval on a recovery request.
     * Enforces dual control for privileged accounts and prevents self-approval.
     */
    public function approve(Request $request, TwoFactorRecoveryApproval $approval): RedirectResponse
    {
        /** @var User $approver */
        $approver = Auth::user();

        $result = $this->enrollmentAuthService->recordApproval($approver, $approval);

        if (! $result['success']) {
            return back()->with('error', $result['error']);
        }

        /** @var TwoFactorRecoveryApproval $updated */
        $updated = $result['approval'];

        // If the approval is now finalized (both approvers for privileged, or 1 for standard)
        if ($updated->status === 'approved') {
            $targetUser = $updated->user;

            // Invalidate existing sessions and credentials on target user
            $targetUser->bumpSecurityVersion();

            // Issue restricted re-enrollment authorization
            $grant = $this->enrollmentAuthService->issueAuthorization(
                $approver,
                $targetUser,
                "Approved lost-device recovery request #{$updated->id}"
            );

            $token = $grant['plain_token'];
            $updated->consumed_at = now();
            $updated->save();

            return back()->with('status', "Recovery request #{$updated->id} is fully approved! Generated single-use re-enrollment token for {$targetUser->full_name}: {$token}")
                ->with('enrollment_token', $token);
        }

        return back()->with('status', "Approval recorded (Slot 1 confirmed). A distinct second authorized approver is required to finalize privileged recovery for {$approval->user->full_name}.");
    }

    /**
     * Reject a recovery request with reason.
     */
    public function reject(Request $request, TwoFactorRecoveryApproval $approval): RedirectResponse
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:255'],
        ]);

        /** @var User $approver */
        $approver = Auth::user();

        if ($approval->user_id === $approver->id) {
            return back()->with('error', 'Cannot action your own recovery request.');
        }

        $approval->update([
            'status' => 'rejected',
            'rejection_reason' => (string) $request->input('rejection_reason'),
        ]);

        AuditLog::create([
            'user_id' => $approver->id,
            'action' => 'mfa_recovery_rejected',
            'table_name' => 'two_factor_recovery_approvals',
            'record_id' => $approval->id,
            'new_values' => ['reason' => $request->input('rejection_reason')],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', "Recovery request #{$approval->id} has been rejected.");
    }

    /**
     * Issue an enrollment authorization for a staff user.
     */
    public function issueEnrollmentToken(Request $request, User $user): RedirectResponse
    {
        /** @var User $authorizer */
        $authorizer = Auth::user();

        if ($user->id === $authorizer->id && $user->hasMfaEnrolled()) {
            return back()->with('error', 'Use the standard factor replacement workflow for your own account.');
        }

        try {
            $result = $this->enrollmentAuthService->issueAuthorization($authorizer, $user, 'Admin-issued enrollment grant');
            $token = $result['plain_token'];

            return back()->with('status', "Single-use enrollment authorization issued for {$user->full_name} ({$user->username}). Token (expires in 10m): {$token}")
                ->with('issued_token', $token)
                ->with('target_user', $user);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
