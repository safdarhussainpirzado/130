<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WomensJourneyUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'womens_journey_id',
        'update_type',
        'content',
        'location_name',
        'latitude',
        'longitude',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * Auto-assign RFC 9562 UUIDv7 on create.
     */
    protected static function booted(): void
    {
        static::creating(function (WomensJourneyUpdate $update): void {
            if (empty($update->uuid)) {
                $update->uuid = (string) Str::uuid7();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function journey(): BelongsTo
    {
        return $this->belongsTo(WomensJourney::class, 'womens_journey_id');
    }
}
