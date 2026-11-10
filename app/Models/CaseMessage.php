<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CaseMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'call_id',
        'sender_user_id',
        'sender_type',
        'audience',
        'message_type',
        'content',
        'media_url',
        'latitude',
        'longitude',
        'metadata',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * Auto-assign RFC 9562 UUIDv7 on create.
     */
    protected static function booted(): void
    {
        static::creating(function (CaseMessage $message): void {
            if (empty($message->uuid)) {
                $message->uuid = (string) Str::uuid7();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class, 'call_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function scopeSharedCase($query)
    {
        return $query->where('audience', 'shared_case');
    }

    public function scopeInternalControl($query)
    {
        return $query->where('audience', 'internal_control');
    }
}
