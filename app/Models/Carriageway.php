<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carriageway extends Model
{
    protected $fillable = [
        'type',
        'road',
        'road_short',
        'road_name',
        'road_from',
        'road_to',
        'total_km',
        'status',
    ];

    protected $casts = [
        'total_km' => 'float',
    ];

    /**
     * Get all landmarks registered along this motorway/highway.
     */
    public function landmarks(): HasMany
    {
        return $this->hasMany(GeospatialLandmark::class, 'road_name', 'road_short');
    }

    /**
     * Resolve all Sectors associated with this Carriageway.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Office>
     */
    public function getSectorsAttribute()
    {
        $code = strtoupper(trim((string) ($this->road_short ?: $this->road)));
        $clean = str_replace('-', '', $code);

        if ($code === 'LEW' || $clean === 'LEW') {
            return Office::where('type', 'sector')->where('id', 42)->get();
        }

        if ($code === 'LEBP' || $clean === 'LEBP') {
            return Office::where('type', 'sector')->where('id', 20)->get();
        }

        return Office::where('type', 'sector')
            ->where(function ($q) use ($code, $clean) {
                if ($code === 'M-3' || $clean === 'M3') {
                    $q->where('id', 19);
                } elseif ($code === 'M-1' || $clean === 'M1') {
                    $q->where('name', 'like', '%M1%')->where('name', 'not like', '%M14%');
                } elseif ($code === 'M-2' || $clean === 'M2') {
                    $q->where('name', 'like', '%M2%');
                } elseif ($code === 'M-4' || $clean === 'M4') {
                    $q->where('name', 'like', '%M4%')->where('name', 'not like', '%M14%');
                } elseif ($code === 'M-5' || $clean === 'M5') {
                    $q->where('name', 'like', '%M5%')->where('name', 'not like', '%N55%');
                } elseif ($code === 'M-14' || $clean === 'M14') {
                    $q->where('name', 'like', '%M14%');
                } elseif ($code === 'E-35' || $clean === 'E35') {
                    $q->where('name', 'like', '%E35%');
                } elseif ($code === 'M-9' || $clean === 'M9') {
                    $q->where('id', 34);
                } elseif ($code === 'M-11' || $clean === 'M11') {
                    $q->where('id', 20);
                } elseif ($code === 'M-16' || str_contains($code, 'SWAT')) {
                    $q->where('name', 'like', '%Swat%');
                } elseif ($code === 'N-5' || $clean === 'N5') {
                    $q->whereHas('parent', function ($zq) {
                        $zq->where('name', 'like', '%N5%');
                    });
                } elseif ($code === 'N-55' || $clean === 'N55') {
                    $q->where('name', 'like', '%N55%');
                } elseif ($code === 'N-25' || $clean === 'N25') {
                    $q->where('name', 'like', '%N-25%')->orWhere('name', 'like', '%N25%');
                } elseif ($code === 'N-10' || $clean === 'N10') {
                    $q->where('name', 'like', '%N10%');
                } elseif ($code === 'N-50' || $clean === 'N50') {
                    $q->where('name', 'like', '%N50%');
                } elseif ($code === 'S-1' || str_contains($code, 'KKH')) {
                    $q->where('name', 'like', '%KKH%')->orWhere('name', 'like', '%S1%');
                } else {
                    $q->where('name', 'like', "%{$clean}%")->orWhere('name', 'like', "%{$code}%");
                }
            })
            ->get();
    }

    /**
     * Resolve all operational Beats assigned to this Carriageway.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Office>
     */
    public function getBeatsAttribute()
    {
        $code = strtoupper(trim((string) ($this->road_short ?: $this->road)));
        $clean = str_replace('-', '', $code);

        if ($code === 'LEW' || $clean === 'LEW') {
            return Office::where('type', 'beat')->where('id', 122)->get();
        }

        if ($code === 'LEBP' || $clean === 'LEBP') {
            return Office::where('type', 'beat')->where('id', 141)->get();
        }

        if ($code === 'M-11' || $clean === 'M11') {
            return Office::where('type', 'beat')->whereIn('id', [70, 71])->orderBy('id')->get();
        }

        $sectorIds = $this->sectors->pluck('id')->all();
        if (empty($sectorIds)) {
            return collect();
        }

        return Office::where('type', 'beat')
            ->whereIn('parent_id', $sectorIds)
            ->whereNotIn('id', [122, 141]) // LEW and LEBP are distinct expressways
            ->orderBy('id')
            ->get();
    }

    /**
     * Get IDs of all beats along this corridor.
     *
     * @return array<int>
     */
    public function getBeatIdsAttribute(): array
    {
        return $this->beats->pluck('id')->all();
    }

    /**
     * Query for physical geospatial markers belonging to this corridor.
     */
    public function markers()
    {
        $beatIds = $this->beat_ids;
        return GeospatialMarker::whereIn('office_id', $beatIds);
    }
}
