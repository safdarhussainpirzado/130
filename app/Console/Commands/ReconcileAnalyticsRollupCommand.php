<?php

namespace App\Console\Commands;

use App\Services\Analytics\CallRollupService;
use Illuminate\Console\Command;

class ReconcileAnalyticsRollupCommand extends Command
{
    protected $signature = 'analytics:reconcile {--start=} {--end=} {--days= : look back N days instead of --start} {--heal : rebuild the window from calls when drift is found}';
    protected $description = 'Reconcile call_hourly_rollups against the raw operational calls table';

    public function handle(CallRollupService $rollupService): int
    {
        $startDate = $this->option('days') ? now()->subDays((int) $this->option('days'))->toDateString() : ($this->option('start') ?: now()->subMonths(6)->toDateString());
        $endDate   = $this->option('end') ?: now()->toDateString();

        $this->info("Reconciling calls table vs call_hourly_rollups between {$startDate} and {$endDate}...");

        $res = $rollupService->reconcile($startDate, $endDate);

        $this->table(
            ['Metric', 'Operational DB (Calls)', 'Rollup Layer (Hourly Rollups)', 'Discrepancy (Delta)'],
            [
                ['Total Calls', number_format($res['raw_total_calls']), number_format($res['rollup_total_calls']), $res['diff_total_calls']],
                ['Completed Calls', number_format($res['raw_completed']), number_format($res['rollup_completed']), $res['diff_completed']],
                ['Pending Calls', number_format($res['raw_pending']), number_format($res['rollup_pending']), $res['diff_pending']],
                ['SLA-eligible (completed)', number_format($res['raw_sla_eligible']), number_format($res['rollup_sla_eligible']), $res['diff_sla_eligible']],
                ['Sum Response Seconds', number_format($res['raw_sum_resp_sec']), number_format($res['rollup_sum_resp_sec']), $res['diff_resp_sec']],
                ['Sum Resolution Seconds', number_format($res['raw_sum_res_sec']), number_format($res['rollup_sum_res_sec']), $res['diff_res_sec']],
            ]
        );

        if (!$res['is_consistent'] && $this->option('heal')) {
            $this->warn("Drift detected: rebuilding rollups for {$startDate}..{$endDate} from the calls table.");
            $rows = $rollupService->backfillHistorical($startDate, $endDate);
            $res = $rollupService->reconcile($startDate, $endDate);
            $this->info("Rebuilt {$rows} rollup rows.");
            \Illuminate\Support\Facades\Log::warning('analytics rollups healed', ['from' => $startDate, 'rows' => $rows]);
        }

        if ($res['is_consistent']) {
            $this->info("VERIFICATION PASSED: Exact 100% data consistency confirmed. Zero drift.");
            return self::SUCCESS;
        } else {
            $this->error("VERIFICATION FAILED: Discrepancy detected between operational and rollup tables.");
            return self::FAILURE;
        }
    }
}
