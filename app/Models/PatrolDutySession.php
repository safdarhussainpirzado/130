<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PatrolDutySession extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'AVAILABLE';
    public const STATUS_BUSY      = 'BUSY';
    public const STATUS_EN_ROUTE  = 'EN_ROUTE';
    public const STATUS_AT_SCENE  = 'AT_SCENE';
    public const STATUS_OFF_DUTY  = 'OFF_DUTY';

    protected $fillable = [
        'uuid',
        'officer_user_id',
        'assisting_officer_user_id',
        'beat_office_id',
        'vehicle_callsign',
        'vehicle_reg_no',
        'status',
        'started_at',
        'ended_at',
        'initial_latitude',
        'initial_longitude',
        'last_latitude',
        'last_longitude',
        'last_speed_kph',
        'last_bearing_degrees',
        'last_carriageway',
        'last_telemetry_at',
    ];

    protected $casts = [
        'started_at'           => 'datetime',
        'ended_at'             => 'datetime',
        'last_telemetry_at'    => 'datetime',
        'initial_latitude'     => 'float',
        'initial_longitude'    => 'float',
        'last_latitude'        => 'float',
        'last_longitude'       => 'float',
        'last_speed_kph'       => 'float',
        'last_bearing_degrees' => 'float',
    ];

    /**
     * Auto-generate RFC 9562 UUIDv7 on PatrolDutySession creation.
     */
    protected static function booted(): void
    {
        static::creating(function (PatrolDutySession $session): void {
            if (empty($session->uuid)) {
                $session->uuid = (string) Str::uuid7();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function primaryOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_user_id');
    }

    public function assistingOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assisting_officer_user_id');
    }

    public function beatOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'beat_office_id');
    }

    public function cases(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Call::class, 'patrol_duty_session_id');
    }

    public function activeCase(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Call::class, 'patrol_duty_session_id')
            ->whereNotIn('status', ['completed', 'cancelled', 'junk'])
            ->latestOfMany();
    }

    public function isActive(): bool
    {
        return $this->status !== self::STATUS_OFF_DUTY && is_null($this->ended_at);
    }
}
