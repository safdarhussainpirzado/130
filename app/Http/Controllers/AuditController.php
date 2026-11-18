<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AuditController extends Controller
{
    /**
     * Display the Enterprise Security Audit Vault.
     */
    public function index(Request $request)
    {
        $this->authorize('system.audit_view');

        $perPage = (int) $request->input('per_page', $request->input('perPage', 20));
        if (!in_array($perPage, [10, 20, 25, 50, 100], true)) {
            $perPage = 20;
        }

        $query = AuditLog::with(['user:id,username,full_name,email,role_id', 'user.role:id,name,display_name'])
            ->orderBy('id', 'desc');

        // Dynamic Category Filtering
        $category = $request->input('category');
        if ($category === 'auth') {
            $query->whereIn('action', ['login_success_mfa_exempt', 'login_failed', 'logout']);
        } elseif ($category === 'mfa') {
            $query->where(function ($q) {
                $q->where('action', 'like', '%2fa%')
                  ->orWhere('action', 'like', '%mfa%')
                  ->orWhere('action', 'like', '%recovery%')
                  ->orWhere('action', 'like', '%step_up%');
            });
        } elseif ($category === 'governance') {
            $query->whereIn('action', [
                'OP_ADMIN_PASSWORD_RESET', 'ADMIN_SECURITY_LOCKOUT_CLEARED',
                'PASSWORD_RESET_TOKEN_ISSUED', 'PASSWORD_RESET_COMPLETED_BY_USER',
                'USER_ORGANIZATIONAL_TRANSFER_COMPLETED', 'ADMIN_ACCOUNT_UNLOCKED',
                'mfa_user_reset_by_admin', 'mfa_user_enforced_by_admin',
                'mfa_user_revoked_by_admin', 'mfa_role_policy_updated'
            ]);
        } elseif ($category === 'security') {
            $query->whereIn('action', [
                'login_failed', '2fa_failed', '2fa_replayed', '2fa_challenge_locked',
                'EMERGENCY_ACCOUNT_LOCKED', 'recovery_code_failed',
                'tamper_detection_test', 'security_gate_test'
            ]);
        }

        // Action / Event Filter
        if ($request->filled('event')) {
            $query->where('action', $request->input('event'));
        }

        // Entity / Table Filter
        if ($request->filled('subject_type')) {
            $query->where('table_name', 'like', '%' . $request->input('subject_type') . '%');
        }

        // Causer / User Filter (ID or username)
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        } elseif ($request->filled('causer')) {
            $causer = $request->input('causer');
            $query->whereHas('user', function ($q) use ($causer) {
                $q->where('username', 'like', "%{$causer}%")
                  ->orWhere('full_name', 'like', "%{$causer}%");
            });
        }

        // IP Address Filter
        if ($request->filled('ip')) {
            $query->where('ip_address', 'like', '%' . $request->input('ip') . '%');
        }

        // Search across action, entity, user, IP
        if ($request->filled('search')) {
            $s = trim($request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('action', 'like', "%{$s}%")
                  ->orWhere('table_name', 'like', "%{$s}%")
                  ->orWhere('ip_address', 'like', "%{$s}%")
                  ->orWhere('record_id', '=', is_numeric($s) ? (int)$s : -1)
                  ->orWhereHas('user', function ($uq) use ($s) {
                      $uq->where('username', 'like', "%{$s}%")
                         ->orWhere('full_name', 'like', "%{$s}%");
                  });
            });
        }

        // Date Range
        if ($request->filled('from')) {
            $query->where('created_at', '>=', Carbon::parse($request->input('from'))->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', Carbon::parse($request->input('to'))->endOfDay());
        }

        $logs = $query->paginate($perPage);

        // Cached Vault KPI Statistics (10 seconds)
        $stats = Cache::remember('audit_vault_summary_stats_v2', 10, function (): mixed {
            $total = DB::table('audit_logs')->count();
            $auth = DB::table('audit_logs')->whereIn('action', ['login_success_mfa_exempt', 'login_failed', 'logout'])->count();
            $mfa = DB::table('audit_logs')->where(function ($q) {
                $q->where('action', 'like', '%2fa%')
                  ->orWhere('action', 'like', '%mfa%')
                  ->orWhere('action', 'like', '%recovery%')
                  ->orWhere('action', 'like', '%step_up%');
            })->count();
            $governance = DB::table('audit_logs')->whereIn('action', [
                'OP_ADMIN_PASSWORD_RESET', 'ADMIN_SECURITY_LOCKOUT_CLEARED',
                'PASSWORD_RESET_TOKEN_ISSUED', 'PASSWORD_RESET_COMPLETED_BY_USER',
                'USER_ORGANIZATIONAL_TRANSFER_COMPLETED', 'ADMIN_ACCOUNT_UNLOCKED',
                'mfa_user_reset_by_admin', 'mfa_user_enforced_by_admin',
                'mfa_user_revoked_by_admin', 'mfa_role_policy_updated'
            ])->count();
            $security = DB::table('audit_logs')->whereIn('action', [
                'login_failed', '2fa_failed', '2fa_replayed', '2fa_challenge_locked',
                'EMERGENCY_ACCOUNT_LOCKED', 'recovery_code_failed',
                'tamper_detection_test', 'security_gate_test'
            ])->count();

            return compact('total', 'auth', 'mfa', 'governance', 'security');
        });

        if ($request->wantsJson()) {
            return response()->json([
                'logs' => $logs,
                'stats' => $stats,
            ]);
        }

        return view('admin.audit.index', compact('logs', 'stats'));
    }

    /**
     * Inspect details of a specific audit log record with cryptographic validation.
     */
    public function show(AuditLog $log): JsonResponse
    {
        $this->authorize('system.audit_view');

        $log->load(['user:id,username,full_name,email,role_id', 'user.role:id,name,display_name']);

        // Verify cryptographic hash integrity for this single record
        $salt = config('app.audit_salt') ?: (function_exists('docker_secret') ? docker_secret('audit_salt', 'nhmp_130_audit_salt') : 'nhmp_130_audit_salt');
        $payload = implode('|', [
            (string) $log->user_id,
            (string) $log->action,
            (string) $log->table_name,
            (string) $log->record_id,
            (string) $log->ip_address,
            json_encode($log->old_values),
            json_encode($log->new_values),
            (string) $log->previous_hash,
        ]);
        $expectedHash = hash_hmac('sha256', $payload, $salt);
        $isTamperProof = hash_equals($expectedHash, (string) $log->record_hash);

        return response()->json([
            'log' => $log,
            'is_tamper_proof' => $isTamperProof,
            'formatted_action' => self::formatActionName($log->action),
            'action_category' => self::categorizeAction($log->action),
        ]);
    }

    /**
     * Human-friendly label for audit actions.
     */
    public static function formatActionName(?string $action): string
    {
        if (!$action) return 'System Event';

        return match ($action) {
            'login_success_mfa_exempt'            => 'Sign-In (MFA Exempt)',
            'login_failed'                        => 'Authentication Failed',
            'logout'                              => 'Session Terminated (Logout)',
            '2fa_verified'                        => '2FA Code Verified',
            '2fa_enrolled'                        => '2FA Authenticator Enrolled',
            '2fa_failed'                          => '2FA Code Verification Failed',
            '2fa_replayed'                        => '2FA Replay Attack Prevented',
            '2fa_challenge_locked'                => '2FA Rate-Limit Lockout',
            '2fa_replaced'                        => '2FA Authenticator Replaced',
            'recovery_code_used'                  => 'Emergency Recovery Code Used',
            'recovery_code_failed'                => 'Emergency Recovery Code Failed',
            'step_up_admin_session_authorized'    => 'Step-Up Security Elevated',
            'step_up_success'                     => 'Step-Up Verification Passed',
            'OP_ADMIN_PASSWORD_RESET'             => 'Administrative Password Reset',
            'PASSWORD_RESET_TOKEN_ISSUED'         => 'Password Recovery Token Issued',
            'PASSWORD_RESET_COMPLETED_BY_USER'    => 'Password Reset Finalized',
            'ADMIN_SECURITY_LOCKOUT_CLEARED'      => 'Security Lockout Cleared by Admin',
            'ADMIN_ACCOUNT_UNLOCKED'              => 'Account Unlocked by Admin',
            'EMERGENCY_ACCOUNT_LOCKED'            => 'Account Emergency Locked',
            'USER_ORGANIZATIONAL_TRANSFER_COMPLETED' => 'Personnel Transfer Executed',
            'mfa_user_enforced_by_admin'          => 'MFA Enforced on User',
            'mfa_user_reset_by_admin'             => 'MFA Reset by Administrator',
            'mfa_user_revoked_by_admin'           => 'MFA Revoked by Administrator',
            'mfa_role_policy_updated'             => 'MFA Role Policy Updated',
            'security_gate_test'                  => 'Automated Security Gate Audit',
            'tamper_detection_test'               => 'Tamper Detection Integrity Probe',
            'patrol_login'                        => 'Patrol App Login',
            'patrol_logout'                       => 'Patrol App Sign-Out',
            'patrol_duty_started'                 => 'Patrol Duty Shift Started',
            'patrol_duty_ended'                   => 'Patrol Duty Shift Concluded',
            'patrol_case_acknowledged'            => 'Emergency Case Acknowledged',
            'patrol_case_en_route'                => 'Patrol En-Route to Scene',
            'patrol_case_arrived'                 => 'Patrol Arrived on Scene',
            'patrol_case_completed'               => 'Assistance Completed & Report Submitted',
            'patrol_case_cannot_respond'          => 'Case Response Declined',
            'patrol_support_requested'            => 'Backup / Support Requested',
            'call_status_in_progress'             => 'Call Initiated / Tiger Dispatched',
            'call_status_tiger_reached'           => 'Tiger Arrived on Scene',
            'call_status_completed'               => 'Call Formally Completed',
            'call_status_cancelled'               => 'Call Cancelled',
            'user_created'                        => 'User Profile Created',
            default                               => ucwords(str_replace(['_', '-'], ' ', $action)),
        };
    }

    /**
     * Categorize action for UI styling.
     */
    public static function categorizeAction(?string $action): string
    {
        if (!$action) return 'system';

        if (in_array($action, ['login_success_mfa_exempt', 'logout', 'patrol_login', 'patrol_logout'])) return 'auth_success';
        if (in_array($action, ['login_failed', '2fa_failed', '2fa_challenge_locked', 'recovery_code_failed', 'EMERGENCY_ACCOUNT_LOCKED', 'patrol_case_cannot_respond'])) return 'danger';
        if (str_contains($action, '2fa') || str_contains($action, 'mfa') || str_contains($action, 'recovery') || str_contains($action, 'step_up') || str_contains($action, 'support_requested')) return 'mfa';
        if (str_contains($action, 'PASSWORD') || str_contains($action, 'LOCKOUT') || str_contains($action, 'TRANSFER') || str_contains($action, 'duty')) return 'governance';

        return 'system';
    }
}
