<?php

namespace App\Console\Commands;

use App\Services\Analytics\CallRollupService;
use Illuminate\Console\Command;

class ProcessAnalyticsOutboxCommand extends Command
{
    protected $signature = 'analytics:process-outbox {--limit=500} {--daemon} {--sleep=2}';
    protected $description = 'Process pending analytics outbox events into call_hourly_rollups';

    public function handle(CallRollupService $rollupService): int
    {
        $limit = (int) $this->option('limit');
        $isDaemon = (bool) $this->option('daemon');
        $sleepSec = (int) $this->option('sleep');

        $this->info("Starting Analytics Outbox Worker (Batch size: {$limit})...");

        do {
            $processed = $rollupService->processOutboxBatch($limit);
            if ($processed > 0) {
                $this->info("[" . now()->toDateTimeString() . "] Processed {$processed} outbox events into rollups.");
            }

            if ($isDaemon) {
                if ($processed === 0) {
                    sleep($sleepSec);
                }
            }
        } while ($isDaemon);

        $this->info("Outbox processing completed.");
        return self::SUCCESS;
    }
}
