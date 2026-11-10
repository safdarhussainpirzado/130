<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TabletAdvisory extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'title',
        'category',
        'advisory_type',
        'severity',
        'route_name',
        'carriageway',
        'office_id',
        'sector_id',
        'zone_id',
        'carriageway_id',
        'start_km',
        'end_km',
        'start_landmark',
        'end_landmark',
        'visibility_meters',
        'speed_limit_kmh',
        'closure_type',
        'message',
        'status',
        'is_emergency',
        'reported_by_tier',
        'corridor_key',
        'start_lat',
        'start_lng',
        'end_lat',
        'end_lng',
        'published_at',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'is_emergency' => 'boolean',
        'start_km' => 'float',
        'end_km' => 'float',
        'visibility_meters' => 'integer',
        'speed_limit_kmh' => 'integer',
        'start_lat' => 'float',
        'start_lng' => 'float',
        'end_lat' => 'float',
        'end_lng' => 'float',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public static function boot(): void
    {
        parent::boot();

        static::creating(function (TabletAdvisory $advisory) {
            if (empty($advisory->uuid)) {
                $advisory->uuid = (string) Str::uuid();
            }
        });

        // Instant Cache Invalidation across all advisory tags
        static::saved(function () {
            self::flushAdvisoryCache();
        });

        static::deleted(function () {
            self::flushAdvisoryCache();
        });
    }

    /**
     * Flush all Redis tagged advisory cache keys.
     */
    public static function flushAdvisoryCache(): void
    {
        try {
            Cache::tags(['tablet_advisories', 'travel_advisory'])->flush();
        } catch (\Throwable $e) {
            // Fallback for non-tagged cache stores
            Cache::forget('tablet_advisories:active_feed');
            Cache::forget('tablet_advisories:emergency_alerts');
            Cache::forget('travel_advisory:ticker');
            Cache::forget('travel_advisory:routes');
        }
    }

    /**
     * Scope for active published advisories.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope for active advisories.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $this->scopePublished($query);
    }

    /**
     * Scope for emergency alerts.
     */
    public function scopeEmergency(Builder $query): Builder
    {
        return $query->where('is_emergency', true);
    }

    /**
     * Scope by route (e.g. M-2, M-1, N-5).
     */
    public function scopeForRoute(Builder $query, string $route): Builder
    {
        $normalized = strtoupper(trim(str_replace('-', '', $route)));
        return $query->where(function ($q) use ($route, $normalized) {
            $q->where('route_name', strtoupper(trim($route)))
              ->orWhere('carriageway', strtoupper(trim($route)))
              ->orWhereRaw("REPLACE(UPPER(route_name), '-', '') = ?", [$normalized]);
        });
    }

    /**
     * Scope by advisory type filter (fog, road_closure, road_works, weather, traffic, diversion).
     */
    public function scopeAdvisoryType(Builder $query, ?string $type): Builder
    {
        if (empty($type) || strtolower($type) === 'all') {
            return $query;
        }

        $typeMap = [
            'closure' => 'road_closure',
            'road_closure' => 'road_closure',
            'fog' => 'fog',
            'fog_visibility' => 'fog',
            'weather' => 'weather',
            'traffic' => 'traffic',
            'roadworks' => 'road_works',
            'road_works' => 'road_works',
            'diversion' => 'diversion',
            'diversions' => 'diversion',
        ];

        $target = $typeMap[strtolower($type)] ?? strtolower($type);

        return $query->where(function ($q) use ($target) {
            $q->where('advisory_type', $target)
              ->orWhere('category', $target);
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'sector_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'zone_id');
    }

    public function carriagewayModel(): BelongsTo
    {
        return $this->belongsTo(Carriageway::class, 'carriageway_id');
    }
}
