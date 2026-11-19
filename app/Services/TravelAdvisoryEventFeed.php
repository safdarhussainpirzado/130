<?php

namespace App\Services;

use App\Models\TabletAdvisory;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Tiny cross-node event feed (Redis cache) for real-time Travel Advisory alerts between sector operators
 * and Call Center supervisors. Events are polled by /ops/travel-advisory/events; failures never break an action.
 */
class TravelAdvisoryEventFeed
{
    private const SEQ = 'ta:events:seq';
    private const LIST = 'ta:events:list';
    private const KEEP = 100;
    private const TTL = 172800;

    public static function push(string $type, TabletAdvisory $advisory, ?User $actor, bool $actorIsSupervisor, array $extra = []): void
    {
        try {
            $event = [
                'seq'                => (int) Cache::increment(self::SEQ),
                'type'               => $type,
                'advisory_id'        => $advisory->id,
                'title'              => Str::limit((string) $advisory->title, 90),
                'route_name'         => $advisory->route_name,
                'start_km'           => $advisory->start_km,
                'end_km'             => $advisory->end_km,
                'sector_id'          => $advisory->sector_id,
                'zone_id'            => $advisory->zone_id,
                'created_by'         => $advisory->created_by,
                'actor_id'           => $actor?->id,
                'actor_name'         => $actor?->full_name ?: $actor?->username,
                'actor_is_supervisor' => $actorIsSupervisor,
                'at'                 => now()->toIso8601String(),
            ] + $extra;

            Cache::lock(self::LIST . ':lock', 5)->block(3, function () use ($event): void {
                $list = Cache::get(self::LIST, []);
                $list[] = $event;
                Cache::put(self::LIST, array_slice($list, -self::KEEP), self::TTL);
            });
        } catch (\Throwable $e) {
            Log::warning('TravelAdvisoryEventFeed push failed: ' . $e->getMessage());
        }
    }

    public static function latestSeq(): int
    {
        return (int) Cache::get(self::SEQ, 0);
    }

    /** @return array<int, array<string, mixed>> */
    public static function since(int $after): array
    {
        return array_values(array_filter(Cache::get(self::LIST, []), fn ($e) => ($e['seq'] ?? 0) > $after));
    }
}
