<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WomensJourney extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'journey_code',
        'user_id',
        'origin_city',
        'origin_stop',
        'destination_city',
        'destination_stop',
        'psv_company_name',
        'psv_reg_no',
        'psv_seat_no',
        'emergency_contacts',
        'status',
        'last_latitude',
        'last_longitude',
        'last_check_in_at',
        'discreet_alert_at',
        'desk_operator_id',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'emergency_contacts' => \App\Casts\GracefulEncrypted::class,
        'last_latitude'      => 'float',
        'last_longitude'     => 'float',
        'last_check_in_at'   => 'datetime',
        'discreet_alert_at'  => 'datetime',
        'completed_at'       => 'datetime',
    ];

    /**
     * Auto-assign RFC 9562 UUIDv7 and Journey Code on create.
     */
    protected static function booted(): void
    {
        static::creating(function (WomensJourney $journey): void {
            if (empty($journey->uuid)) {
                $journey->uuid = (string) Str::uuid7();
            }
            if (empty($journey->journey_code)) {
                $journey->journey_code = 'WJ-' . strtoupper(Str::random(6));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deskOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'desk_operator_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(WomensJourneyUpdate::class, 'womens_journey_id')->orderBy('created_at', 'desc');
    }
}
