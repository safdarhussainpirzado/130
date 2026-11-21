<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PushStatusCommand extends Command
{
    protected $signature = 'push:status {--health : Run healthcheck mode for container and queue consumer monitoring}';

    protected $description = 'Inspect configuration, library status, secrets, queue health, and subscription readiness for unified push notifications';

    public function handle(): int
    {
        $isHealthCheck = (bool) $this->option('health');
        $queueName = config('services.push.queue', 'push');

        // Check Redis connectivity
        $redisHealthy = false;
        $redisError = null;
        try {
            Redis::ping();
            $redisHealthy = true;
        } catch (Throwable $e) {
            $redisError = $e->getMessage();
        }

        // Check worker heartbeat
        $heartbeatRaw = null;
        $heartbeatAgeSeconds = null;
        $workerHealthy = false;
        if ($redisHealthy) {
            try {
                $heartbeatRaw = Redis::get('push:worker:heartbeat');
                if ($heartbeatRaw) {
                    $heartbeatTime = Carbon::parse($heartbeatRaw);
                    $heartbeatAgeSeconds = (int) abs(now()->diffInSeconds($heartbeatTime));
                    $workerHealthy = $heartbeatAgeSeconds <= 180;
                }
            } catch (Throwable) {
                // Ignore parsing errors
            }
        }

        // Check queue depth
        $queueDepth = 0;
        if ($redisHealthy) {
            try {
                $queueDepth = (int) Redis::llen("queues:{$queueName}");
            } catch (Throwable) {
                $queueDepth = 0;
            }
        }

        // Check failed jobs
        $failedJobsCount = 0;
        try {
            if (Schema::hasTable('failed_jobs')) {
                $failedJobsCount = DB::table('failed_jobs')->where('queue', $queueName)->count();
            }
        } catch (Throwable) {
            $failedJobsCount = 0;
        }

        // If invoked with --health, evaluate health and return exit code
        if ($isHealthCheck) {
            if (! $redisHealthy) {
                $this->error("HEALTHCHECK FAILED: Redis unreachable ({$redisError})");

                return 1;
            }

            if (! $workerHealthy) {
                $msg = $heartbeatRaw
                    ? "Worker heartbeat stale ({$heartbeatAgeSeconds}s ago, threshold 180s)"
                    : 'Worker heartbeat missing (push queue worker may not be running)';
                $this->error("HEALTHCHECK FAILED: {$msg}");

                return 1;
            }

            if ($queueDepth > 1000) {
                $this->error("HEALTHCHECK FAILED: Queue depth critical ({$queueDepth} pending jobs)");

                return 1;
            }

            $this->info("OK: Push worker active (heartbeat {$heartbeatAgeSeconds}s ago, queue depth: {$queueDepth}, failed: {$failedJobsCount})");

            return 0;
        }

        // Standard diagnostic output
        $this->info('==========================================================');
        $this->info(' NHMP 130 Unified Push Notification System Diagnostics');
        $this->info('==========================================================');

        $pushEnabled = (bool) config('services.push.enabled', false);
        $fcmEnabled = (bool) config('services.firebase.enabled', false);

        $this->table(
            ['Feature Switch', 'Config Value', 'Operational Status'],
            [
                ['Master Switch (PUSH_ENABLED)', $pushEnabled ? 'true' : 'false', $pushEnabled ? 'ACTIVE' : 'DARK LAUNCH (DISABLED)'],
                ['Firebase Switch (PUSH_FCM_ENABLED)', $fcmEnabled ? 'true' : 'false', $fcmEnabled ? 'ACTIVE' : 'DISABLED'],
                ['Worker Queue Name (PUSH_QUEUE)', $queueName, 'Target Redis Queue'],
            ]
        );

        // 1. Library Status
        $webPushLib = class_exists('Minishlink\WebPush\WebPush');
        $googleAuthLib = class_exists('Google\Auth\Credentials\ServiceAccountCredentials');

        $this->line('');
        $this->comment('1. Dependencies & Libraries:');
        $this->line('   - minishlink/web-push: '.($webPushLib ? '<info>INSTALLED (v11)</info>' : '<error>MISSING</error>'));
        $this->line('   - google/auth:         '.($googleAuthLib ? '<info>INSTALLED (v1)</info>' : '<error>MISSING</error>'));

        // 2. VAPID Keys Status
        $this->line('');
        $this->comment('2. Web Push (RFC 8292 VAPID) Configuration:');
        $pubKey = config('services.webpush.public_key');
        $privKeyFile = config('services.webpush.private_key_file');
        $subject = config('services.webpush.subject');

        $hasPubKey = ! empty($pubKey) && strlen($pubKey) >= 65;
        $hasPrivKeyFile = ! empty($privKeyFile) && file_exists($privKeyFile) && is_readable($privKeyFile);
        $isPrivKeyEmpty = $hasPrivKeyFile && filesize($privKeyFile) === 0;

        $privKeyStatus = '<error>MISSING OR UNREADABLE</error>';
        if ($isPrivKeyEmpty) {
            $privKeyStatus = "<comment>PLACEHOLDER PRESENT (EMPTY - NOT CONFIGURED) [{$privKeyFile}]</comment>";
        } elseif ($hasPrivKeyFile) {
            $privKeyStatus = "<info>SECURE FILE PRESENT [{$privKeyFile}] (mode ".substr(sprintf('%o', fileperms($privKeyFile)), -4).')</info>';
        }

        $this->line('   - Public Key:  '.($hasPubKey ? '<info>PRESENT ('.substr($pubKey, 0, 12).'...)</info>' : '<error>NOT SET (Run php artisan push:generate-vapid)</error>'));
        $this->line("   - Private Key: {$privKeyStatus}");
        $this->line('   - Subject:     '.(! empty($subject) ? "<info>{$subject}</info>" : '<comment>DEFAULT</comment>'));

        // 3. FCM Service Account Status
        $this->line('');
        $this->comment('3. Firebase Cloud Messaging (HTTP v1) Configuration:');
        $projectId = config('services.firebase.project_id');
        $fcmCredsFile = config('services.firebase.credentials_file');
        $hasFcmFile = ! empty($fcmCredsFile) && file_exists($fcmCredsFile) && is_readable($fcmCredsFile);
        $isFcmEmpty = $hasFcmFile && filesize($fcmCredsFile) === 0;

        $fcmStatus = '<comment>NOT FOUND (Optional: drop in secrets/firebase_credentials.json)</comment>';
        if ($isFcmEmpty) {
            $fcmStatus = "<comment>PLACEHOLDER PRESENT (EMPTY - NOT CONFIGURED) [{$fcmCredsFile}]</comment>";
        } elseif ($hasFcmFile) {
            $fcmStatus = "<info>PRESENT [{$fcmCredsFile}]</info>";
        }

        $this->line('   - Project ID:       '.(! empty($projectId) ? "<info>{$projectId}</info>" : '<comment>NOT CONFIGURED</comment>'));
        $this->line("   - Credentials File: {$fcmStatus}");

        // 4. Redis Queue Connection & Worker Health
        $this->line('');
        $this->comment('4. Queue & Storage Infrastructure:');
        $this->line('   - Redis Connection: '.($redisHealthy ? '<info>CONNECTED</info>' : "<error>FAILED: {$redisError}</error>"));
        $this->line("   - Queue Depth ('{$queueName}'): ".($queueDepth > 500 ? "<error>{$queueDepth} pending (HIGH)</error>" : "<info>{$queueDepth} pending</info>"));

        $workerStatus = '<comment>NO HEARTBEAT (Worker not running or idle)</comment>';
        if ($workerHealthy) {
            $workerStatus = "<info>HEALTHY (active {$heartbeatAgeSeconds}s ago)</info>";
        } elseif ($heartbeatRaw) {
            $workerStatus = "<error>STALE (last seen {$heartbeatAgeSeconds}s ago, >180s threshold)</error>";
        }
        $this->line("   - Worker Heartbeat: {$workerStatus}");
        $this->line('   - Failed Push Jobs: '.($failedJobsCount > 0 ? "<error>{$failedJobsCount} failed</error>" : '<info>0</info>'));

        // 5. Database Subscriptions
        try {
            $totalActive = PushSubscription::active()->count();
            $totalWeb = PushSubscription::active()->where('provider', 'webpush')->count();
            $totalFcm = PushSubscription::active()->where('provider', 'fcm')->count();
            $this->line("   - Active Desk Web Push Subscriptions: <info>{$totalWeb}</info>");
            $this->line("   - Active FCM Subscriptions:           <info>{$totalFcm}</info>");
            $this->line("   - Total Active Subscriptions:        <info>{$totalActive}</info>");
        } catch (Throwable) {
            $this->line('   - Database Subscriptions: <comment>Table not yet migrated or DB unavailable</comment>');
        }

        $this->line('');
        $this->info('==========================================================');

        return 0;
    }
}
