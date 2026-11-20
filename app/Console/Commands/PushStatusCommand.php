<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Minishlink\WebPush\WebPush;
use Throwable;

class PushStatusCommand extends Command
{
    protected $signature = 'push:status';

    protected $description = 'Inspect configuration, library status, secrets, and queue readiness for unified push notifications';

    public function handle(): int
    {
        $this->info('==========================================================');
        $this->info(' NHMP 130 Unified Push Notification System Diagnostics');
        $this->info('==========================================================');

        $pushEnabled = (bool) config('services.push.enabled', false);
        $fcmEnabled = (bool) config('services.firebase.enabled', false);
        $queueName = config('services.push.queue', 'push');

        $this->table(
            ['Feature Switch', 'Config Value', 'Operational Status'],
            [
                ['Master Switch (PUSH_ENABLED)', $pushEnabled ? 'true' : 'false', $pushEnabled ? 'ACTIVE' : 'DARK LAUNCH (DISABLED)'],
                ['Firebase Switch (PUSH_FCM_ENABLED)', $fcmEnabled ? 'true' : 'false', $fcmEnabled ? 'ACTIVE' : 'DISABLED'],
                ['Worker Queue Name (PUSH_QUEUE)', $queueName, 'Target Redis Queue'],
            ]
        );

        // 1. Library Status
        $webPushLib = class_exists(WebPush::class);
        $googleAuthLib = class_exists(ServiceAccountCredentials::class);

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

        $this->line('   - Public Key:  '.($hasPubKey ? '<info>PRESENT ('.substr($pubKey, 0, 12).'...)</info>' : '<error>NOT SET (Run php artisan push:generate-vapid)</error>'));
        $this->line('   - Private Key: '.($hasPrivKeyFile ? "<info>SECURE FILE PRESENT [{$privKeyFile}] (mode ".substr(sprintf('%o', fileperms($privKeyFile)), -4).')</info>' : '<error>MISSING OR UNREADABLE</error>'));
        $this->line('   - Subject:     '.(! empty($subject) ? "<info>{$subject}</info>" : '<comment>DEFAULT</comment>'));

        // 3. FCM Service Account Status
        $this->line('');
        $this->comment('3. Firebase Cloud Messaging (HTTP v1) Configuration:');
        $projectId = config('services.firebase.project_id');
        $fcmCredsFile = config('services.firebase.credentials_file');
        $hasFcmFile = ! empty($fcmCredsFile) && file_exists($fcmCredsFile) && is_readable($fcmCredsFile);

        $this->line('   - Project ID:       '.(! empty($projectId) ? "<info>{$projectId}</info>" : '<comment>NOT CONFIGURED</comment>'));
        $this->line('   - Credentials File: '.($hasFcmFile ? "<info>PRESENT [{$fcmCredsFile}]</info>" : '<comment>NOT FOUND (Optional: drop in secrets/firebase_credentials.json)</comment>'));

        // 4. Redis Queue Connection
        $this->line('');
        $this->comment('4. Queue & Storage Infrastructure:');
        $redisStatus = 'UNKNOWN';
        try {
            Redis::ping();
            $redisStatus = '<info>CONNECTED</info>';
        } catch (Throwable $e) {
            $redisStatus = "<error>FAILED: {$e->getMessage()}</error>";
        }
        $this->line("   - Redis Connection: {$redisStatus}");

        // 5. Database Subscriptions
        try {
            $totalActive = PushSubscription::active()->count();
            $totalWeb = PushSubscription::active()->where('provider', 'webpush')->count();
            $totalFcm = PushSubscription::active()->where('provider', 'fcm')->count();
            $this->line("   - Active Desk Web Push Subscriptions: <info>{$totalWeb}</info>");
            $this->line("   - Active FCM Subscriptions:           <info>{$totalFcm}</info>");
            $this->line("   - Total Active Subscriptions:        <info>{$totalActive}</info>");
        } catch (Throwable $e) {
            $this->line('   - Database Subscriptions: <comment>Table not yet migrated or DB unavailable</comment>');
        }

        $this->line('');
        $this->info('==========================================================');

        return 0;
    }
}
