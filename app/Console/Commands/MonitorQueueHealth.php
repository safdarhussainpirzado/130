<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonitorQueueHealth extends Command
{
    protected $signature = 'nhmp:queue-health {--alert-threshold=50 : Max queue depth before triggering alert}';
    protected $description = 'Monitor queue depth, oldest job age, and failed job counts across isolated worker pools';

    public const QUEUES = [
        'emergency_dispatch' => ['sla_seconds' => 5, 'critical' => true],
        'notifications' => ['sla_seconds' => 30, 'critical' => false],
        'analytics_rollup' => ['sla_seconds' => 120, 'critical' => false],
        'heavy_reports' => ['sla_seconds' => 600, 'critical' => false],
        'default' => ['sla_seconds' => 60, 'critical' => false],
    ];

    public function handle(): int
    {
        $prefix = config('database.redis.options.prefix', '');
        $alertThreshold = (int) $this->option('alert-threshold');
        $hasCriticalAlert = false;

        $this->info("================================================================================");
        $this->info(" NHMP 130 QUEUE WORKER POOLS HEALTH AUDIT — " . now()->toIso8601String());
        $this->info("================================================================================");

        $headers = ['Queue Pool', 'Pending Jobs', 'Oldest Job Age (s)', 'SLA Target (s)', 'Status'];
        $rows = [];

        foreach (self::QUEUES as $queueName => $config) {
            $queueKey = "queues:{$queueName}";
            $len = (int) Redis::llen($queueKey);
            
            $oldestAge = 0;
            if ($len > 0) {
                $oldestRaw = Redis::lindex($queueKey, -1);
                if ($oldestRaw) {
                    $decoded = json_decode($oldestRaw, true);
                    $createdAt = $decoded['createdAt'] ?? null;
                    if ($createdAt) {
                        $oldestAge = max(0, time() - (int) $createdAt);
                    }
                }
            }

            $status = 'OK';
            if ($len > $alertThreshold || ($len > 0 && $oldestAge > $config['sla_seconds'])) {
                $status = $config['critical'] ? 'CRITICAL_ALERT' : 'WARNING';
                if ($config['critical']) {
                    $hasCriticalAlert = true;
                }
            }

            $rows[] = [
                $queueName,
                $len,
                $oldestAge,
                $config['sla_seconds'],
                $status
            ];
        }

        $this->table($headers, $rows);

        // Check failed jobs count
        $failedCount = DB::table('failed_jobs')->count();
        $this->info("Failed Jobs Count: {$failedCount}");

        if ($hasCriticalAlert) {
            Log::alert('NHMP 130 Queue Alert: Critical emergency queue backlog exceeded SLA threshold!', [
                'queues' => $rows,
                'failed_count' => $failedCount
            ]);
            $this->error("CRITICAL: One or more emergency queues exceeded SLA thresholds!");
            return 1;
        }

        return 0;
    }
}
