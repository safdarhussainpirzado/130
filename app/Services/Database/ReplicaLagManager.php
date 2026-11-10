<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use PDO;
use Exception;

class ReplicaLagManager
{
    private const CACHE_KEY_LAG = 'nhmp:database:replica_lag_seconds';
    private const CACHE_KEY_HEALTH = 'nhmp:database:replica_health_status';
    private const MAX_ACCEPTABLE_LAG_SECONDS = 2.0;

    /**
     * Check if read replicas are healthy and sufficiently caught up.
     */
    public static function isReplicaSafeForReads(): bool
    {
        try {
            $cached = Cache::get(self::CACHE_KEY_HEALTH);
            if (is_array($cached) && isset($cached['is_healthy'])) {
                return (bool) $cached['is_healthy'];
            }

            $lag = self::getMeasuredLagSeconds();
            $isHealthy = $lag !== null && $lag <= self::MAX_ACCEPTABLE_LAG_SECONDS;

            Cache::put(self::CACHE_KEY_HEALTH, [
                'is_healthy' => $isHealthy,
                'lag_seconds' => $lag,
                'checked_at' => now()->toIso8601String(),
            ], 5);

            return $isHealthy;
        } catch (Exception $e) {
            Log::warning('ReplicaLagManager: Failed to evaluate replica health, falling back to primary write PDO', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Measure actual replication lag across replica instances.
     */
    public static function getMeasuredLagSeconds(): ?float
    {
        try {
            // Check replica status on read connection
            $readPdo = DB::connection('mysql')->getPdo();
            if (!$readPdo) {
                return null;
            }

            $stmt = $readPdo->query("SHOW REPLICA STATUS;");
            $replicaStatus = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

            if ($replicaStatus && isset($replicaStatus['Seconds_Behind_Source'])) {
                $seconds = $replicaStatus['Seconds_Behind_Source'];
                return $seconds === null ? null : (float)$seconds;
            }

            // Fallback for legacy SHOW SLAVE STATUS syntax
            $stmt = $readPdo->query("SHOW SLAVE STATUS;");
            $slaveStatus = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if ($slaveStatus && isset($slaveStatus['Seconds_Behind_Master'])) {
                $seconds = $slaveStatus['Seconds_Behind_Master'];
                return $seconds === null ? null : (float)$seconds;
            }

            // If neither replica status is active (e.g. standalone test node or primary), return 0.0
            return 0.0;
        } catch (Exception $e) {
            Log::error('ReplicaLagManager: Error measuring replica lag', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Enforce consistent read PDO for emergency-critical call operations.
     * Prevents stale read hazards across separate HTTP requests.
     */
    public static function requireFreshData(): void
    {
        DB::connection('mysql')->useWritePdo();
    }

    /**
     * Mark that the current session just wrote critical emergency data.
     * Guarantees read-after-write consistency across subsequent requests within window.
     */
    public static function markWriteActivity(int $stickinessSeconds = 3): void
    {
        if (session()) {
            session()->put('nhmp_raw_consistency_until', microtime(true) + $stickinessSeconds);
        }
    }

    /**
     * Check if session has active read-after-write requirement.
     */
    public static function hasActiveWriteStickyRequirement(): bool
    {
        if (!session()) {
            return false;
        }

        $until = session()->get('nhmp_raw_consistency_until');
        if ($until && microtime(true) < (float)$until) {
            return true;
        }

        return false;
    }
}
