<?php

namespace App\Services\Geo;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class EmergencyOverrideService
{
    public const CACHE_KEY = 'geo_emergency_override_state';
    public const MAX_HOURS = 24;

    /**
     * Check if the emergency override is active.
     */
    public function isActive(): bool
    {
        // 1. Static environment switch check
        if (config('geo_access.emergency_override') === true) {
            return true;
        }

        // 2. Runtime override check
        $state = $this->getState();
        if (empty($state) || !($state['is_active'] ?? false)) {
            return false;
        }

        $expiresAt = isset($state['expires_at']) ? Carbon::parse($state['expires_at']) : null;
        if ($expiresAt === null || now()->greaterThan($expiresAt)) {
            $this->deactivateExpired();
            return false;
        }

        return true;
    }

    /**
     * Get the full emergency override state.
     *
     * @return array{
     *     is_active: bool,
     *     enabled_at: ?string,
     *     expires_at: ?string,
     *     enabled_by: ?string,
     *     reason: ?string,
     *     remaining_minutes: int,
     *     source: string
     * }
     */
    public function getStatus(): array
    {
        if (config('geo_access.emergency_override') === true) {
            return [
                'is_active'         => true,
                'enabled_at'        => null,
                'expires_at'        => null,
                'enabled_by'        => 'env (GEO_EMERGENCY_OVERRIDE)',
                'reason'            => 'Static environment configuration override',
                'remaining_minutes' => -1,
                'source'            => 'env',
            ];
        }

        $state = $this->getState();
        if (empty($state) || !($state['is_active'] ?? false)) {
            return [
                'is_active'         => false,
                'enabled_at'        => null,
                'expires_at'        => null,
                'enabled_by'        => null,
                'reason'            => null,
                'remaining_minutes' => 0,
                'source'            => 'none',
            ];
        }

        $expiresAt = isset($state['expires_at']) ? Carbon::parse($state['expires_at']) : null;
        if ($expiresAt === null || now()->greaterThan($expiresAt)) {
            $this->deactivateExpired();
            return [
                'is_active'         => false,
                'enabled_at'        => null,
                'expires_at'        => null,
                'enabled_by'        => null,
                'reason'            => null,
                'remaining_minutes' => 0,
                'source'            => 'expired',
            ];
        }

        $remaining = (int) ceil(now()->diffInSeconds($expiresAt, false) / 60);

        return [
            'is_active'         => true,
            'enabled_at'        => $state['enabled_at'] ?? null,
            'expires_at'        => $state['expires_at'] ?? null,
            'enabled_by'        => (string) ($state['enabled_by'] ?? 'super_admin'),
            'reason'            => (string) ($state['reason'] ?? ''),
            'remaining_minutes' => max(0, $remaining),
            'source'            => 'runtime',
        ];
    }

    /**
     * Enable the emergency override for up to 24 hours.
     *
     * @param int|string $actorId User ID or username
     * @param int $hours Maximum 24 hours
     * @param string $reason Audit explanation
     * @return array
     */
    public function enable(int|string $actorId, int $hours, string $reason): array
    {
        $durationHours = min(self::MAX_HOURS, max(1, $hours));
        $now = now();
        $expiresAt = $now->copy()->addHours($durationHours);

        $state = [
            'is_active'  => true,
            'enabled_at' => $now->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
            'enabled_by' => (string) $actorId,
            'reason'     => trim($reason) !== '' ? trim($reason) : 'Operational emergency override',
        ];

        $this->saveState($state, $durationHours * 3600);

        // Tamper-evident forensic audit log entry
        try {
            AuditLog::create([
                'user_id'    => is_numeric($actorId) ? (int) $actorId : null,
                'action'     => 'geo_emergency_override_enabled',
                'table_name' => 'geo_access_policies',
                'record_id'  => null,
                'old_values' => [],
                'new_values' => $state,
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'Artisan CLI / System',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed writing emergency override audit log: ' . $e->getMessage());
        }

        return $state;
    }

    /**
     * Disable the emergency override.
     */
    public function disable(int|string $actorId, string $reason = 'Manual deactivation'): void
    {
        $oldState = $this->getState();

        $this->clearState();

        try {
            AuditLog::create([
                'user_id'    => is_numeric($actorId) ? (int) $actorId : null,
                'action'     => 'geo_emergency_override_disabled',
                'table_name' => 'geo_access_policies',
                'record_id'  => null,
                'old_values' => $oldState ?? [],
                'new_values' => [
                    'disabled_at' => now()->toIso8601String(),
                    'disabled_by' => (string) $actorId,
                    'reason'      => $reason,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'Artisan CLI / System',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed writing emergency override deactivation audit log: ' . $e->getMessage());
        }
    }

    private function getState(): ?array
    {
        // Check cache first
        try {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // Redis / cache down -> fall back to file
        }

        // File-based resilience across worker restarts
        $file = $this->getStateFilePath();
        if (File::exists($file)) {
            $content = @file_get_contents($file);
            $decoded = json_decode((string) $content, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function saveState(array $state, int $ttlSeconds): void
    {
        try {
            Cache::put(self::CACHE_KEY, $state, $ttlSeconds);
        } catch (\Throwable $e) {
            // Silently continue to file
        }

        $file = $this->getStateFilePath();
        $dir = dirname($file);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }
        @file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function clearState(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable $e) {
            // Silently continue
        }

        $file = $this->getStateFilePath();
        if (File::exists($file)) {
            @unlink($file);
        }
    }

    private function deactivateExpired(): void
    {
        $this->clearState();
    }

    private function getStateFilePath(): string
    {
        $base = config('geo_access.storage_path', storage_path('app/geo'));
        return $base . '/override_state.json';
    }
}
