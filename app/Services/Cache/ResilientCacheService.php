<?php

namespace App\Services\Cache;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Exception;

class ResilientCacheService
{
    /**
     * Retrieve an item from the cache with stampede protection, TTL jitter, and safe fallback.
     *
     * @template T
     * @param string $key Cache key
     * @param int $baseTtlSeconds Base TTL in seconds
     * @param Closure(): T $callback Computation callback
     * @param float $jitterPercentage Jitter percentage (+/- 10% default)
     * @return T
     */
    public static function rememberWithStampedeProtection(
        string $key,
        int $baseTtlSeconds,
        Closure $callback,
        float $jitterPercentage = 0.10
    ) {
        $jitteredTtl = self::applyJitter($baseTtlSeconds, $jitterPercentage);

        try {
            // Attempt standard cache retrieval
            $cached = Cache::get($key);
            if ($cached !== null) {
                return $cached;
            }

            // Stampede protection via Redis atomic lock
            $lockKey = "nhmp:cache:lock:{$key}";
            $lockAcquired = false;

            try {
                $lockAcquired = (bool) Redis::command('set', [$lockKey, '1', ['NX', 'EX' => 10]]);
            } catch (Exception $e) {
                // Redis might be failing over, proceed with direct computation
                Log::warning('ResilientCacheService: Redis lock failed, falling back to direct calculation', [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }

            if ($lockAcquired) {
                try {
                    $value = $callback();
                    try {
                        Cache::put($key, $value, $jitteredTtl);
                    } catch (Exception $e) {
                        Log::error('ResilientCacheService: Cache store failed during Redis degradation', [
                            'key' => $key,
                            'error' => $e->getMessage()
                        ]);
                    }
                    return $value;
                } finally {
                    try {
                        Redis::del($lockKey);
                    } catch (Exception $e) {
                        // ignore unlock failure
                    }
                }
            } else {
                // Another worker is recalculating; wait briefly (100ms) and retry get once
                usleep(100000);
                try {
                    $retryValue = Cache::get($key);
                    if ($retryValue !== null) {
                        return $retryValue;
                    }
                } catch (Exception $e) {
                    // ignore
                }

                // If still not cached, calculate directly to avoid blocking caller
                return $callback();
            }
        } catch (Exception $e) {
            // Redis completely down or unreachable -> safe fallback to direct computation
            Log::emergency('ResilientCacheService: Redis unavailable! Safe database/computation fallback active', [
                'key' => $key,
                'exception' => $e->getMessage()
            ]);
            return $callback();
        }
    }

    /**
     * Apply +/- random jitter to base TTL to prevent thundering herd cache expiry.
     */
    public static function applyJitter(int $baseTtl, float $jitterPct = 0.10): int
    {
        $range = (int) round($baseTtl * $jitterPct);
        if ($range <= 0) {
            return $baseTtl;
        }

        $jitter = random_int(-$range, $range);
        return max(5, $baseTtl + $jitter);
    }

    /**
     * Acquire an atomic distributed lock with jittered retry.
     */
    public static function acquireLock(string $resource, int $leaseSeconds = 15, int $waitTimeoutMs = 2000): bool
    {
        $lockKey = "nhmp:lock:{$resource}";
        $start = microtime(true);

        while ((microtime(true) - $start) * 1000 < $waitTimeoutMs) {
            try {
                if (Redis::command('set', [$lockKey, 'locked', ['NX', 'EX' => $leaseSeconds]])) {
                    return true;
                }
            } catch (Exception $e) {
                return false;
            }

            usleep(random_int(20000, 50000)); // 20-50ms jittered backoff
        }

        return false;
    }

    /**
     * Release an atomic distributed lock.
     */
    public static function releaseLock(string $resource): void
    {
        try {
            Redis::del("nhmp:lock:{$resource}");
        } catch (Exception $e) {
            // Ignore failure on lock cleanup
        }
    }
}
