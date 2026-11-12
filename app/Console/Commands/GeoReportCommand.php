<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\LoginLog;
use App\Services\Geo\EmergencyOverrideService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GeoReportCommand extends Command
{
    protected $signature = 'geo:report
                            {--days=7 : Number of days to analyze (default: 7)}
                            {--limit=10 : Table row limit}';

    protected $description = 'Generate operational Geo access report (top foreign countries, ASNs, flagged users)';

    public function handle(EmergencyOverrideService $overrideService): int
    {
        $days = max(1, (int) $this->option('days'));
        $limit = max(5, (int) $this->option('limit'));
        $since = now()->subDays($days);

        $this->info("================================================================================");
        $this->info(" NHMP 130 Helpline — Geographic Access & Shadow Evaluation Report ({$days} Days)");
        $this->info("================================================================================");

        $status = $overrideService->getStatus();
        $mode = (string) config('geo_access.mode', 'shadow');

        $this->table(
            ['System Setting', 'Current Status'],
            [
                ['GEO_ACCESS_MODE', strtoupper($mode) . ($mode === 'shadow' ? ' (Log-only, safe for operators)' : ($mode === 'enforce' ? ' (Strict blocking active)' : ' (Disabled)'))],
                ['Emergency Override', $status['is_active'] ? 'ACTIVE (ALL ENFORCEMENT BYPASSED)' : 'INACTIVE (Standard evaluation)'],
                ['Analysis Window', "Since {$since->toDateTimeString()} ({$days} days)"],
            ]
        );

        // 1. Decision Breakdown
        $decisions = LoginLog::where('login_at', '>=', $since)
            ->whereNotNull('geo_decision')
            ->select('geo_decision', DB::raw('count(*) as count'))
            ->groupBy('geo_decision')
            ->pluck('count', 'geo_decision')
            ->toArray();

        // Also check audit_logs for explicit geo actions
        $auditCounts = AuditLog::where('created_at', '>=', $since)
            ->whereIn('action', ['geo_login_allowed', 'geo_login_alert', 'geo_login_shadow_would_deny', 'geo_login_denied'])
            ->select('action', DB::raw('count(*) as count'))
            ->groupBy('action')
            ->pluck('count', 'action')
            ->toArray();

        $this->newLine();
        $this->info("1. Login Decision Distribution");
        $this->table(
            ['Decision / Action', 'Total Events', 'Description'],
            [
                ['allow / geo_login_allowed', $auditCounts['geo_login_allowed'] ?? ($decisions['allow'] ?? 0), 'Normal authorized logins from Pakistan / allow-list'],
                ['allow_with_alert / geo_login_alert', $auditCounts['geo_login_alert'] ?? ($decisions['allow_with_alert'] ?? 0), 'Operator allowed with risk alert (mobile proxy / new device)'],
                ['shadow_would_deny / geo_login_shadow_would_deny', $auditCounts['geo_login_shadow_would_deny'] ?? ($decisions['shadow_would_deny'] ?? 0), '<fg=yellow>Shadow mode events that WOULD be blocked in enforce mode</>'],
                ['deny / geo_login_denied', $auditCounts['geo_login_denied'] ?? ($decisions['deny'] ?? 0), '<fg=red>Blocked logins (enforce mode only)</>'],
            ]
        );

        // 2. Top Foreign Countries Attempting Login
        $foreignCountries = LoginLog::where('login_at', '>=', $since)
            ->whereNotNull('country')
            ->where('country', '!=', 'PK')
            ->select('country', DB::raw('count(*) as total'), DB::raw('count(distinct user_id) as users_count'))
            ->groupBy('country')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $this->newLine();
        $this->info("2. Top Foreign Countries Attempting Login");
        if ($foreignCountries->isEmpty()) {
            $this->line("  No foreign country logins detected in the last {$days} days.");
        } else {
            $rows = $foreignCountries->map(fn ($r) => [
                'Country Code' => $r->country,
                'Total Logins' => $r->total,
                'Unique Users' => $r->users_count,
            ])->toArray();
            $this->table(['Country Code', 'Total Logins', 'Unique Users'], $rows);
        }

        // 3. Top ASNs / Organizations
        $topAsns = LoginLog::where('login_at', '>=', $since)
            ->whereNotNull('asn')
            ->select('asn', 'org', 'country', DB::raw('count(*) as total'))
            ->groupBy('asn', 'org', 'country')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $this->newLine();
        $this->info("3. Top Networks & ASNs");
        if ($topAsns->isEmpty()) {
            $this->line("  No ASN data recorded in the last {$days} days.");
        } else {
            $rows = $topAsns->map(fn ($r) => [
                'ASN'          => 'AS' . $r->asn,
                'Organization' => $r->org ?: 'N/A',
                'Country'      => $r->country ?: 'N/A',
                'Logins'       => $r->total,
            ])->toArray();
            $this->table(['ASN', 'Organization', 'Country', 'Logins'], $rows);
        }

        // 4. Users with Alerts / Potential Denials
        $alertedUsers = LoginLog::with('user:id,username,full_name,role_id', 'user.role:id,name')
            ->where('login_at', '>=', $since)
            ->whereIn('geo_decision', ['allow_with_alert', 'shadow_would_deny', 'deny'])
            ->select('user_id', 'geo_decision', DB::raw('count(*) as total'))
            ->groupBy('user_id', 'geo_decision')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $this->newLine();
        $this->info("4. Flagged Operator Accounts");
        if ($alertedUsers->isEmpty()) {
            $this->line("  No users with alerts or potential denials in the last {$days} days.");
        } else {
            $rows = $alertedUsers->map(fn ($r) => [
                'Username' => $r->user?->username ?? ("User #" . $r->user_id),
                'Role'     => $r->user?->role?->name ?? 'N/A',
                'Decision' => $r->geo_decision,
                'Count'    => $r->total,
            ])->toArray();
            $this->table(['Username', 'Role', 'Decision', 'Count'], $rows);
        }

        // 5. Recent Flagged Logins
        $recent = LoginLog::with('user:id,username,role_id', 'user.role:id,name')
            ->where('login_at', '>=', $since)
            ->whereIn('geo_decision', ['allow_with_alert', 'shadow_would_deny', 'deny'])
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        $this->newLine();
        $this->info("5. Most Recent Flagged Login Events");
        if ($recent->isEmpty()) {
            $this->line("  None.");
        } else {
            $rows = $recent->map(fn ($r) => [
                'Time'        => $r->login_at?->toDateTimeString(),
                'User'        => $r->user?->username ?? "User #{$r->user_id}",
                'IP'          => $r->ip_address,
                'Country'     => $r->country ?: 'N/A',
                'ASN'         => $r->asn ? "AS{$r->asn}" : 'N/A',
                'Decision'    => $r->geo_decision,
                'Reasons'     => implode(', ', (array) ($r->risk_reasons ?? [])),
            ])->toArray();
            $this->table(['Time', 'User', 'IP', 'Country', 'ASN', 'Decision', 'Reasons'], $rows);
        }

        $this->newLine();
        return Command::SUCCESS;
    }
}
