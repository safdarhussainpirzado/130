<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PatrolOfficerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'badge_number',
        'assigned_beat_id',
        'device_uuid',
        'device_fcm_token',
        'is_on_duty',
        'current_duty_session_id',
    ];

    protected $casts = [
        'is_on_duty' => 'boolean',
    ];

    /**
     * Auto-generate RFC 9562 UUIDv7 on PatrolOfficerProfile creation.
     */
    protected static function booted(): void
    {
        static::creating(function (PatrolOfficerProfile $profile): void {
            if (empty($profile->uuid)) {
                $profile->uuid = (string) Str::uuid7();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBeat(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'assigned_beat_id');
    }

    public function currentDutySession(): BelongsTo
    {
        return $this->belongsTo(PatrolDutySession::class, 'current_duty_session_id', 'uuid');
    }
}
