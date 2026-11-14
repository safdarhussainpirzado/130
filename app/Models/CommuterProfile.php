<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CommuterProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'google_id',
        'avatar_url',
        'gender',
        'blood_group',
        'is_cnic_verified',
        'emergency_contacts',
        'vehicle_profiles',
        'saved_routes',
        'preferred_language',
        'device_fcm_token',
    ];

    protected $casts = [
        'is_cnic_verified'   => 'boolean',
        'emergency_contacts' => \App\Casts\GracefulEncrypted::class,
        'vehicle_profiles'   => 'array',
        'saved_routes'       => 'array',
    ];

    /**
     * Auto-generate RFC 9562 UUIDv7 on CommuterProfile creation.
     */
    protected static function booted(): void
    {
        static::creating(function (CommuterProfile $profile): void {
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

    public function getFullNameAttribute(): ?string
    {
        return $this->user?->full_name;
    }

    public function getNameAttribute(): ?string
    {
        return $this->user?->full_name;
    }

    /**
     * Check if safety profile contains basic requirements (mobile, gender, contacts).
     */
    public function isProfileComplete(): bool
    {
        $hasMobile = ! empty($this->user?->mobile_no);
        $hasGender = ! empty($this->gender);
        $hasEmergencyContacts = ! empty($this->emergency_contacts) && is_array($this->emergency_contacts) && count($this->emergency_contacts) > 0;

        return $hasMobile && $hasGender && $hasEmergencyContacts;
    }
}
