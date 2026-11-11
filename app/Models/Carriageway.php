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

        if (in_array($code, ['E-75', 'N-75', 'IMDC']) || in_array($clean, ['E75', 'N75', 'IMDC'])) {
            return Office::where('type', 'sector')->where('id', 28)->get(); // Sector 28 IMDC
        }

        if ($code === 'N-5' || $clean === 'N5') {
            // N-5 Sectors strictly: North1(25), North2(26), North3(27), Central1(29), Central2(30), Central3(31), South1(32), South2(33), South3(34)
            // Sector 28 (IMDC / E-75 / N-75), Sector 35 (N55-1), Sector 40 (N55-2), Sector 42 (South4 M-9/LEW) are strictly isolated
            return Office::where('type', 'sector')
                ->whereIn('id', [25, 26, 27, 29, 30, 31, 32, 33, 34])
                ->orderBy('id')
                ->get();
        }

        if ($code === 'N-55' || $clean === 'N55') {
            return Office::where('type', 'sector')->whereIn('id', [35, 40])->orderBy('id')->get();
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
                    $q->whereIn('id', [34, 42]);
                } elseif ($code === 'M-11' || $clean === 'M11') {
                    $q->where('id', 20);
                } elseif ($code === 'M-16' || str_contains($code, 'SWAT')) {
                    $q->where('name', 'like', '%Swat%');
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

        if ($code === 'M-9' || $clean === 'M9') {
            return Office::where('type', 'beat')->whereIn('id', [119, 120, 121])->orderBy('id')->get();
        }

        if (in_array($code, ['E-75', 'N-75', 'IMDC']) || in_array($clean, ['E75', 'N75', 'IMDC'])) {
            return Office::where('type', 'beat')->where('id', 97)->get();
        }

        if ($code === 'N-55' || $clean === 'N55') {
            return Office::where('type', 'beat')->whereIn('id', [123, 124, 125, 126, 127, 128])->orderBy('id')->get();
        }

        if ($code === 'N-5' || $clean === 'N5') {
            // N-5 Beats strictly: 86-96, 98-118 (32 total operational beats along Grand Trunk Road corridor)
            // Excludes Beat 97 (IMDC/E-75/N-75), Beats 119-121 (M-9), Beat 122 (LEW), Beats 123-128 (N-55), Beat 141 (LEBP)
            return Office::where('type', 'beat')
                ->whereIn('id', [
                    86, 87, 88, 89, 90, 91, 92, 93, 94, 95, 96,
                    98, 99, 100, 101, 102, 103, 104, 105, 106,
                    107, 108, 109, 110, 111, 112, 113, 114, 115, 116, 117, 118
                ])
                ->orderBy('id')
                ->get();
        }

        $sectorIds = $this->sectors->pluck('id')->all();
        if (empty($sectorIds)) {
            return collect();
        }

        return Office::where('type', 'beat')
            ->whereIn('parent_id', $sectorIds)
            ->whereNotIn('id', [97, 119, 120, 121, 122, 123, 124, 125, 126, 127, 128, 141])
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
