<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficerPresence extends Model
{
    public const ONLINE_WINDOW_SECONDS = 180;
    public const AVAILABILITY = ['available', 'prayer_break', 'meal_break', 'official_assignment', 'temporary_break', 'other'];

    protected $table = 'officer_presence';

    protected $fillable = ['user_id', 'last_seen_at', 'last_lat', 'last_lng', 'app_version', 'availability'];

    protected $casts = ['last_seen_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gte(now()->subSeconds(self::ONLINE_WINDOW_SECONDS));
    }
}
