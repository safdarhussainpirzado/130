<?php

namespace App\Console\Commands;

use App\Services\Analytics\CallRollupService;
use Illuminate\Console\Command;

class BackfillRollupAnalyticsCommand extends Command
{
    protected $signature = 'analytics:backfill {--start=} {--end=}';
    protected $description = 'Backfill historical call aggregations into call_hourly_rollups';

    public function handle(CallRollupService $rollupService): int
    {
        $startDate = $this->option('start') ?: now()->subMonths(6)->toDateString();
        $endDate   = $this->option('end') ?: now()->toDateString();

        $this->info("Backfilling analytics from {$startDate} to {$endDate}...");
        $startTime = microtime(true);

        $inserted = $rollupService->backfillHistorical($startDate, $endDate);
        $elapsed = round(microtime(true) - $startTime, 2);

        $this->info("SUCCESS: Backfilled {$inserted} hourly rollup rows in {$elapsed}s.");
        return self::SUCCESS;
    }
}
