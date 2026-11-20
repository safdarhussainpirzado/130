<?php

namespace App\Services\Notifications;

use App\Models\PushSubscription;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FcmService
{
    private const OAUTH_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const CACHE_KEY_TOKEN = 'push:fcm:access_token';

    private const TOKEN_CACHE_TTL_SEC = 3000; // ~50 minutes

    /**
     * Retrieve OAuth2 Bearer token using Google Auth Service Account credentials.
     * Caches in Redis/Cache for ~50 minutes.
     * Fails closed if credentials or configuration are absent.
     */
    public function getAccessToken(): ?string
    {
        if (Cache::has(self::CACHE_KEY_TOKEN)) {
            $cached = Cache::get(self::CACHE_KEY_TOKEN);
            if (! empty($cached) && is_string($cached)) {
                return $cached;
            }
        }

        $credsFile = (string) config('services.firebase.credentials_file');
        if (empty($credsFile) || ! file_exists($credsFile) || ! is_readable($credsFile) || filesize($credsFile) === 0) {
            return null;
        }

        try {
            $credentials = new ServiceAccountCredentials(self::OAUTH_SCOPE, $credsFile);
            $authToken = $credentials->fetchAuthToken();
            $token = $authToken['access_token'] ?? null;

            if (! empty($token)) {
                Cache::put(self::CACHE_KEY_TOKEN, $token, self::TOKEN_CACHE_TTL_SEC);

                return $token;
            }
        } catch (Throwable $e) {
            $this->logRateLimited('error', 'FCM OAuth token acquisition failed: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Send FCM notifications via HTTP v1 API.
     *
     * @param  array<string>  $tokens
     * @param  array{title: string, body: string}  $notification
     * @param  array<string, string>  $data
     * @return array{success: bool, sent: int, failed: int, error?: string, errors?: array}
     */
    public function sendToTokens(array $tokens, array $notification, array $data = []): array
    {
        $tokens = array_values(array_filter(array_unique($tokens)));
        if (empty($tokens)) {
            return ['success' => true, 'sent' => 0, 'failed' => 0];
        }

        $masterEnabled = (bool) config('services.push.enabled', false);
        $fcmEnabled = (bool) config('services.firebase.enabled', false);

        if (! $masterEnabled || ! $fcmEnabled) {
            $this->logRateLimited('info', 'FCM push skipped: Master push or Firebase FCM is not enabled in config.');

            return [
                'success' => false,
                'error' => 'NOT_CONFIGURED',
                'sent' => 0,
                'failed' => count($tokens),
            ];
        }

        $credsFile = (string) config('services.firebase.credentials_file');
        $projectId = config('services.firebase.project_id');

        if (empty($projectId) && ! empty($credsFile) && file_exists($credsFile) && is_readable($credsFile) && filesize($credsFile) > 0) {
            $parsed = json_decode((string) file_get_contents($credsFile), true);
            $projectId = $parsed['project_id'] ?? null;
        }

        if (empty($projectId)) {
            $this->logRateLimited('warning', 'FCM push failed: FIREBASE_PROJECT_ID is not configured.');

            return [
                'success' => false,
                'error' => 'NOT_CONFIGURED',
                'sent' => 0,
                'failed' => count($tokens),
            ];
        }

        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            $this->logRateLimited('warning', 'FCM push failed: Could not acquire valid OAuth access token.');

            return [
                'success' => false,
                'error' => 'NOT_CONFIGURED',
                'sent' => 0,
                'failed' => count($tokens),
            ];
        }

        // Ensure all data values are stringified for FCM HTTP v1 compliance
        $sanitizedData = [];
        foreach ($data as $k => $v) {
            $sanitizedData[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
        }

        $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $sent = 0;
        $failed = 0;
        $errors = [];

        // Concurrently dispatch requests in chunks using Http::pool
        $chunks = array_chunk($tokens, 50);
        foreach ($chunks as $chunk) {
            $responses = Http::pool(function ($pool) use ($chunk, $endpoint, $accessToken, $notification, $sanitizedData) {
                foreach ($chunk as $token) {
                    $pool->as($token)->withToken($accessToken)
                        ->timeout(10)
                        ->post($endpoint, [
                            'message' => [
                                'token' => $token,
                                'notification' => [
                                    'title' => (string) ($notification['title'] ?? ''),
                                    'body' => (string) ($notification['body'] ?? ''),
                                ],
                                'data' => $sanitizedData,
                                'android' => [
                                    'priority' => 'high',
                                ],
                                'webpush' => [
                                    'headers' => [
                                        'Urgency' => 'high',
                                    ],
                                ],
                            ],
                        ]);
                }
            });

            foreach ($chunk as $token) {
                $response = $responses[$token] ?? null;

                if ($response instanceof Response && $response->successful()) {
                    $sent++;
                } else {
                    $failed++;
                    $status = $response instanceof Response ? $response->status() : 0;
                    $body = $response instanceof Response ? $response->body() : (is_string($response) ? $response : 'Connection failure');

                    $errors[$token] = [
                        'status' => $status,
                        'body' => substr($body, 0, 300),
                    ];

                    // Deactivate dead / unregistered device tokens
                    if (
                        $status === 404 ||
                        str_contains($body, 'UNREGISTERED') ||
                        str_contains($body, 'INVALID_ARGUMENT') ||
                        str_contains($body, 'NOT_FOUND')
                    ) {
                        try {
                            PushSubscription::deactivateSubscription($token);
                        } catch (Throwable) {
                            // Non-blocking
                        }
                    }
                }
            }
        }

        return [
            'success' => $sent > 0,
            'sent' => $sent,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Rate-limited logger to prevent log flooding during outages.
     */
    private function logRateLimited(string $level, string $message): void
    {
        $logKey = 'push:fcm:log_ratelimit:'.md5($message);
        if (Cache::add($logKey, true, 3600)) {
            Log::log($level, $message);
        }
    }
}
