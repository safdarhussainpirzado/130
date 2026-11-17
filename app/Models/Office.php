<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Office extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'type',
        'parent_id',
        'operational_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });

        static::saved(function () {
            static::clearHierarchyCache();
        });

        static::deleted(function () {
            static::clearHierarchyCache();
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function () {
                static::clearHierarchyCache();
            });
        }
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Office::class, 'parent_id');
    }

    public function sourceSupportRequests(): HasMany
    {
        return $this->hasMany(SupportRequest::class, 'source_office_id');
    }

    public function destinationSupportRequests(): HasMany
    {
        return $this->hasMany(SupportRequest::class, 'destination_office_id');
    }

    /**
     * Resolve all descendant IDs using in-memory cached parent-child adjacency list.
     * Hardened against:
     * - Graph cycles (A -> B -> A) via visited lookup set
     * - Self-referencing loops (parent_id == id)
     * - Cache/Redis connection failures (safe database fallback)
     * - Duplicate node accumulation
     */
    public static function getDescendantIdsFor(int $officeId, bool $includeSelf = true): array
    {
        $allOffices = static::getHierarchyTree();

        $descendants = [];
        $visited = [];
        $queue = [$officeId];
        $visited[$officeId] = true;

        if ($includeSelf) {
            $descendants[] = $officeId;
        }

        while (!empty($queue)) {
            $currentId = array_shift($queue);

            if (isset($allOffices[$currentId])) {
                foreach ($allOffices[$currentId] as $child) {
                    $childId = (int) $child['id'];

                    // Cycle detection and self-reference guard
                    if (!isset($visited[$childId])) {
                        $visited[$childId] = true;
                        $descendants[] = $childId;
                        $queue[] = $childId;
                    }
                }
            }
        }

        return $descendants;
    }

    /**
     * Retrieve the global parent-child office adjacency map.
     * Resilient against Redis/Cache outages with direct DB fallback.
     */
    public static function getHierarchyTree(): array
    {
        $fetchFromDb = function (): array {
            try {
                $offices = static::select('id', 'parent_id', 'is_active')
                    ->whereNull('deleted_at')
                    ->get();

                $tree = [];
                foreach ($offices as $office) {
                    $parentId = $office->parent_id !== null ? (int) $office->parent_id : 0;
                    $tree[$parentId][] = [
                        'id' => (int) $office->id,
                        'parent_id' => $parentId,
                        'is_active' => (bool) $office->is_active,
                    ];
                }
                return $tree;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Office hierarchy DB query failed: ' . $e->getMessage());
                return [];
            }
        };

        try {
            return \Illuminate\Support\Facades\Cache::remember('crm:all_offices_tree_v1', 3600, $fetchFromDb);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Office hierarchy cache unavailable, falling back to direct DB: ' . $e->getMessage());
            return $fetchFromDb();
        }
    }

    /**
     * Lightweight id => office row map (name, type, parent, KM range, phone) cached for 10 minutes.
     * Used by hot AJAX lookups so they never eager-load Office relations (invalidated with the hierarchy tree).
     *
     * @return array<int, array{id:int,name:string,type:string,parent_id:?int,km_start:?float,km_end:?float,is_active:bool,phone:?string}>
     */
    public static function liteMap(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('crm:offices_lite_v1', 600, function (): array {
            $map = [];
            foreach (static::query()->select('id', 'name', 'type', 'parent_id', 'km_start', 'km_end', 'is_active', 'phone')->get() as $o) {
                $map[(int) $o->id] = [
                    'id' => (int) $o->id, 'name' => (string) $o->name, 'type' => (string) $o->type,
                    'parent_id' => $o->parent_id ? (int) $o->parent_id : null,
                    'km_start' => $o->km_start !== null ? (float) $o->km_start : null,
                    'km_end' => $o->km_end !== null ? (float) $o->km_end : null,
                    'is_active' => (bool) $o->is_active,
                    'phone' => $o->phone !== null && trim((string) $o->phone) !== '' ? trim((string) $o->phone) : null,
                ];
            }
            return $map;
        });
    }

    /**
     * Atomically invalidate the global office hierarchy cache across all workers.
     */
    public static function clearHierarchyCache(): void
    {
        try {
            \Illuminate\Support\Facades\Cache::forget('crm:all_offices_tree_v1');
            \Illuminate\Support\Facades\Cache::forget('crm:offices_lite_v1');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to clear office hierarchy cache: ' . $e->getMessage());
        }
    }

    /**
     * Get all descendant IDs of this office, including itself if $includeSelf is true.
     */
    public function getDescendantIds(bool $includeSelf = true): array
    {
        if ($this->id) {
            return static::getDescendantIdsFor((int) $this->id, $includeSelf);
        }

        $ids = $includeSelf ? [$this->id] : [];
        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->getDescendantIds(true));
        }
        return $ids;
    }

    /**
     * Get the root office (Region).
     */
    public function region(): ?Office
    {
        if ($this->type === 'region') {
            return $this;
        }
        return $this->parent ? $this->parent->region() : null;
    }

    /**
     * Get the Zone.
     */
    public function zone(): ?Office
    {
        if ($this->type === 'zone') {
            return $this;
        }
        return $this->parent ? $this->parent->zone() : null;
    }

    /**
     * Get the Sector.
     */
    public function sector(): ?Office
    {
        if ($this->type === 'sector') {
            return $this;
        }
        return $this->parent ? $this->parent->sector() : null;
    }

    /**
     * Resolve the Carriageway (Motorway/Highway) this Office belongs to as an Eloquent accessor.
     */
    public function getCarriagewayAttribute(): ?Carriageway
    {
        if ($this->type === 'beat') {
            if ($this->id === 122 || str_contains($this->name, 'LEW')) {
                return Carriageway::where('road', 'LEW')->first();
            }
            if ($this->id === 141 || str_contains($this->name, 'LEBP')) {
                return Carriageway::where('road', 'LEBP')->first();
            }
            if (in_array($this->id, [119, 120, 121])) {
                return Carriageway::where('road', 'M9')->orWhere('road_short', 'M-9')->first();
            }
            if ($this->id === 97 || str_contains($this->name, 'IMDC')) {
                return Carriageway::where('road_short', 'E-75')
                    ->orWhere('road_short', 'N-75')
                    ->orWhere('road', 'E75')
                    ->orWhere('road', 'N75')
                    ->first();
            }
            if (in_array($this->id, [123, 124, 125, 126, 127, 128])) {
                return Carriageway::where('road_short', 'N-55')->orWhere('road', 'N55')->first();
            }
            if (in_array($this->id, [70, 71])) {
                return Carriageway::where('road', 'M11')->orWhere('road_short', 'M-11')->first();
            }
            if ($this->parent) {
                return $this->parent->carriageway;
            }
        }

        if ($this->type === 'sector') {
            $name = $this->name;
            if ($this->id === 28 || str_contains($name, 'IMDC')) {
                return Carriageway::where('road_short', 'E-75')
                    ->orWhere('road_short', 'N-75')
                    ->orWhere('road', 'E75')
                    ->orWhere('road', 'N75')
                    ->first();
            }
            if (in_array($this->id, [35, 40]) || str_contains($name, 'N55')) {
                return Carriageway::where('road_short', 'N-55')->orWhere('road', 'N55')->first();
            }
            if ($this->id === 42 || str_contains($name, 'South4')) {
                return Carriageway::where('road', 'M9')->orWhere('road_short', 'M-9')->first();
            }
            if (str_contains($name, 'M14')) return Carriageway::where('road', 'M14')->first();
            if (str_contains($name, 'M1'))  return Carriageway::where('road', 'M1')->first();
            if (str_contains($name, 'M2'))  return Carriageway::where('road', 'M2')->first();
            if (str_contains($name, 'M3'))  return Carriageway::where('road', 'M3')->first();
            if (str_contains($name, 'LSM')) return Carriageway::where('road', 'M11')->orWhere('road_short', 'M-11')->first();
            if (str_contains($name, 'M4'))  return Carriageway::where('road', 'M4')->first();
            if (str_contains($name, 'M5'))  return Carriageway::where('road', 'M5')->first();
            if (str_contains($name, 'M9'))  return Carriageway::where('road', 'M9')->first();
            if (str_contains($name, 'E35')) return Carriageway::where('road', 'E35')->first();
            if (str_contains($name, 'Swat')) return Carriageway::where('road', 'M16')->orWhere('road_name', 'like', '%Swat%')->first();
            if (str_contains($name, 'N-25') || str_contains($name, 'N25')) return Carriageway::where('road', 'N25')->first();
            if (str_contains($name, 'N10')) return Carriageway::where('road', 'N-10')->first();
            if (str_contains($name, 'N50')) return Carriageway::where('road', 'N50')->first();
            if (str_contains($name, 'KKH')) return Carriageway::where('road', 'S1')->first();

            $zoneName = $this->parent?->name ?? '';
            if (str_contains($zoneName, 'N5')) {
                return Carriageway::where('road', 'N5')->orWhere('road_short', 'N-5')->first();
            }
        }

        return null;
    }

    // Scopes for specific types
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeRegions(Builder $query): Builder
    {
        return $query->where('type', 'region');
    }

    public function scopeZones(Builder $query): Builder
    {
        return $query->where('type', 'zone');
    }

    public function scopeSectors(Builder $query): Builder
    {
        return $query->where('type', 'sector');
    }

    public function scopeBeats(Builder $query): Builder
    {
        return $query->where('type', 'beat');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
