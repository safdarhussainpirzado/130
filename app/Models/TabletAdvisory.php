<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TabletAdvisory extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'title',
        'category',
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
        'approved_at',
        'expires_at',
        'created_by',
        'approved_by',
        'rejection_reason',
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
        'approved_at' => 'datetime',
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

        // Ensure GPS coordinates are populated before saving
        static::saving(function (TabletAdvisory $advisory) {
            $advisory->ensureCoordinates();
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
            // Ignore if tagged cache is unsupported
        }

        // Direct key invalidations for ResilientCacheService and API endpoints
        Cache::forget('tablet_advisories:active_feed');
        Cache::forget('tablet_advisories:emergency_alerts');
        Cache::forget('travel_advisory:ticker');
        Cache::forget('travel_advisory:public:ticker:v1');
        Cache::forget('travel_advisory:routes');
        Cache::forget('travel_advisory:routes:v1');
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
     * Scope for pending approval advisories awaiting supervisor review.
     */
    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('status', 'pending_approval');
    }

    /**
     * Scope for active approved advisories.
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
     * Scope by route (e.g. M-2, M-1, N-5, or 'ALL' for nationwide).
     */
    public function scopeForRoute(Builder $query, ?string $route): Builder
    {
        if (empty($route) || strtoupper(trim($route)) === 'ALL') {
            return $query;
        }

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

        return $query->where('category', $target);
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Approve and broadcast the advisory live to public feeds.
     */
    public function approve(int|User $approver): bool
    {
        $approverId = ($approver instanceof User) ? $approver->id : $approver;
        $now = Carbon::now();

        $this->status = 'published';
        $this->approved_by = $approverId;
        $this->approved_at = $now;
        if (empty($this->published_at)) {
            $this->published_at = $now;
        }

        $saved = $this->save();
        if ($saved) {
            self::flushAdvisoryCache();
        }
        return $saved;
    }

    /**
     * Reject advisory with optional explanation reason.
     */
    public function reject(int|User $reviewer, ?string $reason = null): bool
    {
        $reviewerId = ($reviewer instanceof User) ? $reviewer->id : $reviewer;

        $this->status = 'rejected';
        $this->approved_by = $reviewerId;
        $this->rejection_reason = $reason;

        $saved = $this->save();
        if ($saved) {
            self::flushAdvisoryCache();
        }
        return $saved;
    }

    /**
     * Automatically ensure coordinates are populated using GeospatialMarker, Carriageway, or route defaults.
     */
    public function ensureCoordinates(): void
    {
        $needsStart = is_null($this->start_lat) || is_null($this->start_lng);
        $needsEnd   = is_null($this->end_lat) || is_null($this->end_lng);

        if (! $needsStart && ! $needsEnd) {
            return;
        }

        $cleanRoute = strtoupper(str_replace(['-', ' '], '', $this->route_name ?? ''));
        $cw = $this->carriagewayModel
            ?: Carriageway::where('road', $cleanRoute)
                ->orWhere('road_short', $this->route_name)
                ->first();

        // 1. Try resolving via Carriageway markers (has 377k+ physical high-precision markers)
        if ($cw) {
            if ($needsStart && $this->start_km !== null) {
                $marker = $cw->markers()
                    ->whereNotNull('lat')
                    ->whereNotNull('lng')
                    ->orderByRaw('ABS(km - ?)', [(float)$this->start_km])
                    ->first();
                if ($marker) {
                    $this->start_lat = (float)$marker->lat;
                    $this->start_lng = (float)$marker->lng;
                    $needsStart = false;
                }
            }

            if ($needsEnd) {
                $targetKm = $this->end_km !== null ? (float)$this->end_km : (float)$this->start_km;
                $marker = $cw->markers()
                    ->whereNotNull('lat')
                    ->whereNotNull('lng')
                    ->orderByRaw('ABS(km - ?)', [$targetKm])
                    ->first();
                if ($marker) {
                    $this->end_lat = (float)$marker->lat;
                    $this->end_lng = (float)$marker->lng;
                    $needsEnd = false;
                }
            }
        }

        // 2. Try resolving via Office hierarchy (office or sector child beats)
        if (($needsStart || $needsEnd) && ($this->office_id || $this->sector_id)) {
            $officeId = $this->office_id ?: $this->sector_id;
            $office = Office::find($officeId);
            $beatIds = [];
            if ($office) {
                if ($office->type === 'beat') {
                    $beatIds = [$office->id];
                } elseif ($office->type === 'sector') {
                    $beatIds = Office::where('parent_id', $office->id)->pluck('id')->all();
                }
            }

            if (!empty($beatIds)) {
                if ($needsStart && $this->start_km !== null) {
                    $marker = GeospatialMarker::whereIn('office_id', $beatIds)
                        ->whereNotNull('lat')
                        ->whereNotNull('lng')
                        ->orderByRaw('ABS(km - ?)', [(float)$this->start_km])
                        ->first();
                    if ($marker) {
                        $this->start_lat = (float)$marker->lat;
                        $this->start_lng = (float)$marker->lng;
                        $needsStart = false;
                    }
                }

                if ($needsEnd) {
                    $targetKm = $this->end_km !== null ? (float)$this->end_km : (float)$this->start_km;
                    $marker = GeospatialMarker::whereIn('office_id', $beatIds)
                        ->whereNotNull('lat')
                        ->whereNotNull('lng')
                        ->orderByRaw('ABS(km - ?)', [$targetKm])
                        ->first();
                    if ($marker) {
                        $this->end_lat = (float)$marker->lat;
                        $this->end_lng = (float)$marker->lng;
                        $needsEnd = false;
                    }
                }
            }
        }

        // 3. Fallback: Standard known corridor coordinates for major Pakistani highways & motorways
        if ($needsStart || $needsEnd) {
            $defaultCoords = self::getRouteDefaultCoordinates($this->route_name);
            if ($defaultCoords) {
                if ($needsStart) {
                    $this->start_lat = (float)$defaultCoords[0][0];
                    $this->start_lng = (float)$defaultCoords[0][1];
                }
                if ($needsEnd) {
                    $this->end_lat = (float)($defaultCoords[1][0] ?? $defaultCoords[0][0]);
                    $this->end_lng = (float)($defaultCoords[1][1] ?? $defaultCoords[0][1]);
                }
            }
        }
    }

    /**
     * Get default coordinate pairs [start, end] for Pakistani motorways and national highways.
     */
    public static function getRouteDefaultCoordinates(?string $route): ?array
    {
        if (! $route) {
            return [[31.5204, 74.3587], [33.6844, 73.0479]]; // Default Islamabad-Lahore
        }

        $clean = strtoupper(str_replace(['-', ' '], '', $route));

        return match ($clean) {
            'M1'   => [[33.6844, 73.0479], [34.0151, 71.5249]], // Islamabad - Peshawar
            'M2'   => [[31.5204, 74.3587], [33.6844, 73.0479]], // Lahore - Islamabad
            'M3'   => [[31.5204, 74.3587], [30.1575, 71.5249]], // Lahore - Abdul Hakim
            'M4'   => [[31.8900, 73.2700], [30.1575, 71.5249]], // Pindi Bhattian - Multan
            'M5'   => [[30.1575, 71.5249], [27.7244, 68.8228]], // Multan - Sukkur
            'M9'   => [[24.8607, 67.0011], [25.3960, 68.3578]], // Karachi - Hyderabad
            'M11'  => [[31.5204, 74.3587], [32.4945, 74.5229]], // Lahore - Sialkot
            'M14'  => [[33.5500, 72.8000], [31.8300, 70.9000]], // Hakla - D.I. Khan
            'M16'  => [[34.0200, 71.9800], [34.6500, 72.0300]], // Swat Expressway
            'E35'  => [[33.8200, 72.7500], [34.7800, 73.0200]], // Hassan Abdal - Thakot
            'N5'   => [[24.8607, 67.0011], [34.0151, 71.5249]], // Karachi - Peshawar (GT Road)
            'N25'  => [[24.8607, 67.0011], [30.9200, 66.4500]], // Karachi - Chaman
            'N55'  => [[25.4200, 68.2800], [33.5500, 71.4500]], // Indus Highway
            'N10'  => [[25.4000, 65.7000], [25.1200, 62.3200]], // Makran Coastal Highway
            'N65'  => [[27.7244, 68.8228], [30.1798, 66.9750]], // Sukkur - Quetta
            'N70'  => [[30.1575, 71.5249], [30.6975, 68.4230]], // Multan - Qila Saifullah
            'N50'  => [[30.3400, 66.9900], [31.8300, 70.9000]], // Kuchlak - D.I Khan
            'N75'  => [[33.6844, 73.0479], [33.9070, 73.3943]], // Islamabad - Murree
            'E75'  => [[33.6844, 73.0479], [33.9070, 73.3943]], // Islamabad - Murree
            'LEW'  => [[24.8717, 66.9779], [24.9700, 67.0800]], // Lyari Expressway
            'LEBP' => [[31.6200, 74.3800], [31.5800, 74.4200]], // Lahore Eastern Bypass
            default => [[31.5204, 74.3587], [33.6844, 73.0479]],
        };
    }
}
