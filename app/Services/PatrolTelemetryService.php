<?php

namespace App\Services;

use App\Models\PatrolDutySession;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class PatrolTelemetryService
{
    public const REDIS_GEO_KEY = 'nhmp:patrols:live';
    public const REDIS_HASH_PREFIX = 'nhmp:patrol:';
    public const TELEMETRY_TTL_SECONDS = 900; // 15 minutes

    /**
     * Ingest and buffer live GPS telemetry into Redis and update the duty session.
     */
    public function recordTelemetry(PatrolDutySession $session, array $data): array
    {
        $lat = (float) $data['latitude'];
        $lng = (float) $data['longitude'];
        $speed = (float) $data['speed_kph'];
        $bearing = (float) $data['bearing_degrees'];
        $carriageway = $data['carriageway'] ?? $session->last_carriageway;
        $accuracy = isset($data['accuracy_meters']) ? (float) $data['accuracy_meters'] : null;
        $timestamp = isset($data['timestamp']) ? (string) $data['timestamp'] : now()->toISOString();

        // 1. Buffer into Redis Geospatial Index & Metadata Hash
        try {
            Redis::geoadd(self::REDIS_GEO_KEY, $lng, $lat, $session->uuid);

            $hashKey = self::REDIS_HASH_PREFIX . $session->uuid . ':telemetry';
            Redis::hMSet($hashKey, [
                'session_uuid'     => $session->uuid,
                'vehicle_callsign' => (string) $session->vehicle_callsign,
                'officer_name'     => (string) ($session->primaryOfficer?->full_name ?? 'Officer'),
                'beat_office_id'   => (string) $session->beat_office_id,
                'beat_name'        => (string) ($session->beatOffice?->name ?? 'Beat'),
                'latitude'         => (string) $lat,
                'longitude'        => (string) $lng,
                'speed_kph'        => (string) $speed,
                'bearing_degrees'  => (string) $bearing,
                'carriageway'      => (string) ($carriageway ?? ''),
                'accuracy_meters'  => (string) ($accuracy ?? ''),
                'status'           => (string) $session->status,
                'updated_at'       => $timestamp,
            ]);
            Redis::expire($hashKey, self::TELEMETRY_TTL_SECONDS);
        } catch (\Throwable $e) {
            Log::warning('PatrolTelemetryService Redis buffer exception: ' . $e->getMessage());
        }

        // 2. Rollup to MySQL session state
        $session->update([
            'last_latitude'        => $lat,
            'last_longitude'       => $lng,
            'last_speed_kph'       => $speed,
            'last_bearing_degrees' => $bearing,
            'last_carriageway'     => $carriageway,
            'last_telemetry_at'    => now(),
        ]);

        // 3. Check for pending assignments or active case
        $pendingCount = \App\Models\Call::where('patrol_duty_session_id', $session->id)
            ->where('status', 'assigned')
            ->count();

        $activeCase = $session->activeCase()->first();

        return [
            'status'                    => 'recorded',
            'server_time'               => now()->toISOString(),
            'pending_assignments_count' => $pendingCount,
            'active_case_uuid'          => $activeCase?->uuid,
        ];
    }

    /**
     * Get live telemetry for a given duty session from Redis with DB fallback.
     */
    public function getLivePatrolPosition(PatrolDutySession $session): array
    {
        try {
            $hashKey = self::REDIS_HASH_PREFIX . $session->uuid . ':telemetry';
            $cached = Redis::hGetAll($hashKey);
            if (!empty($cached) && isset($cached['latitude'])) {
                return [
                    'source'           => 'redis_live',
                    'session_uuid'     => $session->uuid,
                    'vehicle_callsign' => $cached['vehicle_callsign'] ?? $session->vehicle_callsign,
                    'latitude'         => (float) $cached['latitude'],
                    'longitude'        => (float) $cached['longitude'],
                    'speed_kph'        => (float) ($cached['speed_kph'] ?? 0),
                    'bearing_degrees'  => (float) ($cached['bearing_degrees'] ?? 0),
                    'carriageway'      => $cached['carriageway'] ?? $session->last_carriageway,
                    'updated_at'       => $cached['updated_at'] ?? now()->toISOString(),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('PatrolTelemetryService Redis lookup error: ' . $e->getMessage());
        }

        return [
            'source'           => 'db_fallback',
            'session_uuid'     => $session->uuid,
            'vehicle_callsign' => $session->vehicle_callsign,
            'latitude'         => (float) ($session->last_latitude ?? $session->initial_latitude ?? 0),
            'longitude'        => (float) ($session->last_longitude ?? $session->initial_longitude ?? 0),
            'speed_kph'        => (float) ($session->last_speed_kph ?? 0),
            'bearing_degrees'  => (float) ($session->last_bearing_degrees ?? 0),
            'carriageway'      => $session->last_carriageway,
            'updated_at'       => $session->last_telemetry_at?->toISOString() ?? now()->toISOString(),
        ];
    }
}
