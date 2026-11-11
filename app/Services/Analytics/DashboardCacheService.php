<?php

namespace App\Services\Analytics;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DashboardCacheService
{
    /**
     * Stale-While-Revalidate (SWR) with Single-Flight Locking and TTL Jitter.
     *
     * @param string $key Base cache key
     * @param int $ttlSeconds Fresh TTL duration in seconds
     * @param int $staleGraceSeconds Grace period to serve stale content while refreshing
     * @param Closure $regenerate Callback to generate fresh data
     * @param array $context Permission/scope context array for key isolation
     * @return mixed
     */
    public function rememberSwr(
        string $key,
        int $ttlSeconds,
        int $staleGraceSeconds,
        Closure $regenerate,
        array $context = []
    ): mixed {
        // Construct permission/scope isolated cache key
        $scopeKeyPart = !empty($context) ? md5(json_encode($context)) : 'global';
        $fullKey = "dash_swr:{$key}:{$scopeKeyPart}";
        $lockKey = "lock:dash_swr:{$key}:{$scopeKeyPart}";

        try {
            $cached = Cache::get($fullKey);
        } catch (\Throwable $e) {
            Log::warning("Redis connection failure for key {$fullKey}; falling back to direct computation: " . $e->getMessage());
            return $regenerate();
        }

        $now = time();

        if ($cached !== null && is_array($cached) && isset($cached['payload'], $cached['expires_at'])) {
            // If data is still within fresh TTL, serve instantly
            if ($now < $cached['expires_at']) {
                return $cached['payload'];
            }

            // Data is in Stale window (expires_at <= now < grace_expires_at)
            if ($now < $cached['grace_expires_at']) {
                // Attempt to acquire non-blocking single-flight lock
                $lock = Cache::lock($lockKey, 15);
                if ($lock->get()) {
                    // This request won the right to refresh; compute fresh data
                    try {
                        $freshPayload = $regenerate();
                        $this->storePayload($fullKey, $freshPayload, $ttlSeconds, $staleGraceSeconds);
                        $lock->release();
                        return $freshPayload;
                    } catch (\Throwable $e) {
                        $lock->release();
                        Log::error("Failed to refresh stale dashboard cache {$fullKey}: " . $e->getMessage());
                        // Return existing stale payload on computation failure
                        return $cached['payload'];
                    }
                }

                // Another worker/request is actively regenerating; serve stale payload immediately (< 2ms)
                return $cached['payload'];
            }
        }

        // Cache Miss or Grace Period completely expired: Must compute synchronously with lock
        $lock = Cache::lock($lockKey, 20);
        try {
            $freshPayload = $lock->block(5, function () use ($regenerate) {
                return $regenerate();
            });
            $this->storePayload($fullKey, $freshPayload, $ttlSeconds, $staleGraceSeconds);
            return $freshPayload;
        } catch (\Throwable $e) {
            Log::warning("Lock contention or timeout on {$fullKey}; calculating directly: " . $e->getMessage());
            return $regenerate();
        }
    }

    /**
     * Store payload with jittered TTL to prevent synchronized thundering herds.
     */
    protected function storePayload(string $fullKey, mixed $payload, int $ttlSeconds, int $staleGraceSeconds): void
    {
        // Add 5% - 15% random jitter to TTL
        $jitter = rand(1, max(2, (int) ($ttlSeconds * 0.15)));
        $actualTtl = $ttlSeconds + $jitter;
        $totalStorageSec = $actualTtl + $staleGraceSeconds;

        $envelope = [
            'payload'          => $payload,
            'cached_at'        => time(),
            'expires_at'       => time() + $actualTtl,
            'grace_expires_at' => time() + $totalStorageSec,
        ];

        try {
            Cache::put($fullKey, $envelope, $totalStorageSec);
        } catch (\Throwable $e) {
            Log::error("Failed to put key into Redis: " . $e->getMessage());
        }
    }

    /**
     * Invalidate specific dashboard cache scope.
     */
    public function invalidate(string $key, array $context = []): void
    {
        $scopeKeyPart = !empty($context) ? md5(json_encode($context)) : 'global';
        $fullKey = "dash_swr:{$key}:{$scopeKeyPart}";
        try {
            Cache::forget($fullKey);
        } catch (\Throwable $e) {
            Log::warning("Failed to invalidate cache key {$fullKey}: " . $e->getMessage());
        }
    }
}
