<?php

namespace App\Services\Notifications;

use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\NullLogger;
use Throwable;

class WebPushService
{
    /**
     * Send Web Push notifications to a collection of PushSubscription models.
     *
     * @param  Collection<int, PushSubscription>|array<int, PushSubscription>  $subscriptions
     * @param  array{title: string, body: string, icon?: string, badge?: string, data?: array}  $payload
     * @return array{success: bool, sent: int, failed: int, error?: string}
     */
    public function send(Collection|array $subscriptions, array $payload): array
    {
        $subscriptions = $subscriptions instanceof Collection ? $subscriptions : collect($subscriptions);
        $total = $subscriptions->count();

        if ($total === 0) {
            return ['success' => true, 'sent' => 0, 'failed' => 0];
        }

        $masterEnabled = (bool) config('services.push.enabled', false);
        $webPushEnabled = (bool) config('services.webpush.enabled', false);

        if (! $masterEnabled || ! $webPushEnabled) {
            $this->logRateLimited('info', 'Web Push skipped: Master push or WebPush is not enabled in config.');

            return [
                'success' => false,
                'error' => 'NOT_CONFIGURED',
                'sent' => 0,
                'failed' => $total,
            ];
        }

        if (! class_exists(WebPush::class)) {
            $this->logRateLimited('error', 'Web Push failed: minishlink/web-push library is not installed.');

            return [
                'success' => false,
                'error' => 'NOT_CONFIGURED',
                'sent' => 0,
                'failed' => $total,
            ];
        }

        $publicKey = (string) config('services.webpush.public_key');
        $privateKey = (string) config('services.webpush.private_key');

        if (empty($privateKey)) {
            $keyFile = (string) config('services.webpush.private_key_file');
            if (! empty($keyFile) && file_exists($keyFile) && is_readable($keyFile)) {
                $privateKey = trim((string) file_get_contents($keyFile));
            }
        }

        if (empty($publicKey) || empty($privateKey)) {
            $this->logRateLimited('warning', 'Web Push failed: RFC 8292 VAPID keys are missing or unreadable.');

            return [
                'success' => false,
                'error' => 'NOT_CONFIGURED',
                'sent' => 0,
                'failed' => $total,
            ];
        }

        $subject = (string) config('services.webpush.subject', 'mailto:admin@130.nhmp.gov.pk');
        $payloadJson = json_encode($payload);

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => $subject,
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ], [], null, null, null, null, new NullLogger);

            $webPush->setDefaultOptions([
                'TTL' => 3600,
                'urgency' => 'high',
            ]);

            foreach ($subscriptions as $subRecord) {
                if (empty($subRecord->endpoint) || empty($subRecord->public_key) || empty($subRecord->auth_token)) {
                    continue;
                }

                $subscription = Subscription::create([
                    'endpoint' => $subRecord->endpoint,
                    'publicKey' => $subRecord->public_key,
                    'authToken' => $subRecord->auth_token,
                    'contentEncoding' => 'aesgcm',
                ]);

                $webPush->queueNotification($subscription, $payloadJson);
            }

            $sent = 0;
            $failed = 0;

            /** @var MessageSentReport $report */
            foreach ($webPush->flush() as $report) {
                $endpoint = (string) $report->getEndpoint();

                if ($report->isSuccess()) {
                    $sent++;
                } else {
                    $failed++;
                    $response = $report->getResponse();
                    $statusCode = $response ? $response->getStatusCode() : 0;

                    // 404 Not Found or 410 Gone indicates expired or revoked subscription
                    if (in_array($statusCode, [404, 410], true) || $report->isSubscriptionExpired()) {
                        try {
                            PushSubscription::deactivateSubscription($endpoint);
                        } catch (Throwable) {
                            // Non-blocking
                        }
                    }
                }
            }

            return [
                'success' => $sent > 0,
                'sent' => $sent,
                'failed' => $failed,
            ];
        } catch (Throwable $e) {
            $this->logRateLimited('error', 'Web Push execution failure: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'sent' => 0,
                'failed' => $total,
            ];
        }
    }

    /**
     * Alias for send() supporting iterables.
     */
    public function sendToSubscriptions(iterable $subscriptions, array $payload): array
    {
        return $this->send($subscriptions instanceof Collection ? $subscriptions : collect($subscriptions), $payload);
    }

    /**
     * Rate-limited logger to prevent log flooding.
     */
    private function logRateLimited(string $level, string $message): void
    {
        $logKey = 'push:webpush:log_ratelimit:'.md5($message);
        if (Cache::add($logKey, true, 3600)) {
            Log::log($level, $message);
        }
    }
}
