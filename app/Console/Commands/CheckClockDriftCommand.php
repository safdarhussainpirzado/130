<?php

namespace App\Console\Commands;

use App\Services\TwoFactor\TotpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckClockDriftCommand extends Command
{
    protected $signature = 'auth:check-clock-drift {--max-drift=5 : Maximum acceptable clock drift in seconds}';
    protected $description = 'Verify server time synchronization and monitor cluster clock drift for TOTP security.';

    public function handle(TotpService $totpService): int
    {
        $maxDrift = (int) $this->option('max-drift');
        $this->info("Checking server clock synchronization (max allowed drift: {$maxDrift}s)...");

        $result = $totpService->checkClockSync($maxDrift);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Application Node Time', date('Y-m-d H:i:s T', $result['app_time']) . " ({$result['app_time']})"],
                ['Database Cluster Time', date('Y-m-d H:i:s T', $result['db_time']) . " ({$result['db_time']})"],
                ['Measured Drift', "{$result['drift_seconds']} second(s)"],
                ['Sync Status', $result['synchronized'] ? '<info>SYNCHRONIZED</info>' : '<error>DRIFT DETECTED</error>'],
            ]
        );

        if (!$result['synchronized']) {
            $this->error("CRITICAL: Clock drift ({$result['drift_seconds']}s) exceeds maximum threshold of {$maxDrift}s!");
            $this->error("TOTP authentication will be blocked until NTP synchronization is restored.");

            Log::critical('Cluster clock drift exceeded threshold: TOTP authentication gate alerted', [
                'drift_seconds' => $result['drift_seconds'],
                'app_time' => $result['app_time'],
                'db_time' => $result['db_time'],
            ]);

            return Command::FAILURE;
        }

        $this->info("Server clock synchronization is healthy. TOTP verification step windows are aligned.");
        return Command::SUCCESS;
    }
}
