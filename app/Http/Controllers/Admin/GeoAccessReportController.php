<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LoginLog;
use App\Services\Geo\EmergencyOverrideService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GeoAccessReportController extends Controller
{
    public function __construct(
        private EmergencyOverrideService $overrideService
    ) {}

    /**
     * Display the Geographic Access & Shadow Enforcement Report.
     */
    public function index(Request $request): View
    {
        // Super Admin only; everyone else sees a 404 so the page is not discoverable.
        abort_unless($request->user()?->role?->name === \App\Models\Role::SUPER_ADMIN, 404);
        $this->authorize('system.audit_view');

        $days = (int) $request->input('days', 7);
        if (!in_array($days, [1, 7, 14, 30, 90], true)) {
            $days = 7;
        }

        $since = now()->subDays($days);
        $overrideStatus = $this->overrideService->getStatus();
        $mode = (string) config('geo_access.mode', 'shadow');

        // 1. Overall Decision Statistics
        $decisions = LoginLog::where('login_at', '>=', $since)
            ->whereNotNull('geo_decision')
            ->select('geo_decision', DB::raw('count(*) as count'))
            ->groupBy('geo_decision')
            ->pluck('count', 'geo_decision')
            ->toArray();

        $auditCounts = AuditLog::where('created_at', '>=', $since)
            ->whereIn('action', ['geo_login_allowed', 'geo_login_alert', 'geo_login_shadow_would_deny', 'geo_login_denied'])
            ->select('action', DB::raw('count(*) as count'))
            ->groupBy('action')
            ->pluck('count', 'action')
            ->toArray();

        $totalEvaluations = ($auditCounts['geo_login_allowed'] ?? ($decisions['allow'] ?? 0))
            + ($auditCounts['geo_login_alert'] ?? ($decisions['allow_with_alert'] ?? 0))
            + ($auditCounts['geo_login_shadow_would_deny'] ?? ($decisions['shadow_would_deny'] ?? 0))
            + ($auditCounts['geo_login_denied'] ?? ($decisions['deny'] ?? 0));

        $stats = [
            'total'             => $totalEvaluations,
            'allowed'           => $auditCounts['geo_login_allowed'] ?? ($decisions['allow'] ?? 0),
            'alert'             => $auditCounts['geo_login_alert'] ?? ($decisions['allow_with_alert'] ?? 0),
            'shadow_would_deny' => $auditCounts['geo_login_shadow_would_deny'] ?? ($decisions['shadow_would_deny'] ?? 0),
            'denied'            => $auditCounts['geo_login_denied'] ?? ($decisions['deny'] ?? 0),
        ];

        // 2. Top Foreign Countries
        $foreignCountries = LoginLog::where('login_at', '>=', $since)
            ->whereNotNull('country')
            ->where('country', '!=', 'PK')
            ->select('country', DB::raw('count(*) as total'), DB::raw('count(distinct user_id) as users_count'))
            ->groupBy('country')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // 3. Top Networks & ASNs
        $topAsns = LoginLog::where('login_at', '>=', $since)
            ->whereNotNull('asn')
            ->select('asn', 'org', 'country', DB::raw('count(*) as total'))
            ->groupBy('asn', 'org', 'country')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // 4. Flagged Operators
        $flaggedUsers = LoginLog::with('user:id,username,full_name,role_id', 'user.role:id,name,display_name')
            ->where('login_at', '>=', $since)
            ->whereIn('geo_decision', ['allow_with_alert', 'shadow_would_deny', 'deny'])
            ->select('user_id', 'geo_decision', DB::raw('count(*) as total'))
            ->groupBy('user_id', 'geo_decision')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        // 5. Recent Flagged Login Feed
        $recentLogins = LoginLog::with('user:id,username,full_name,role_id', 'user.role:id,name,display_name')
            ->where('login_at', '>=', $since)
            ->whereIn('geo_decision', ['allow_with_alert', 'shadow_would_deny', 'deny'])
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.geo-access.report', compact(
            'mode',
            'overrideStatus',
            'days',
            'since',
            'stats',
            'foreignCountries',
            'topAsns',
            'flaggedUsers',
            'recentLogins'
        ));
    }
}
