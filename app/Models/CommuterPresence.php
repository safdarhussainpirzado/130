<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommuterPresence extends Model
{
    public const ONLINE_WINDOW_SECONDS = 300;

    protected $table = 'commuter_presence';

    protected $fillable = ['user_id', 'lat', 'lng', 'heading', 'speed', 'sharing', 'last_seen_at'];

    protected $casts = ['sharing' => 'boolean', 'last_seen_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
