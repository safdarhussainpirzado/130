<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'office_id',
        'device_type',
        'provider',
        'token_hash',
        'endpoint',
        'public_key',
        'auth_token',
        'user_agent',
        'is_active',
        'last_used_at',
    ];

    /**
     * Keys and auth secrets are encrypted at rest using project standard cipher.
     */
    protected $casts = [
        'public_key' => 'encrypted',
        'auth_token' => 'encrypted',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForOffice(Builder $query, int $officeId): Builder
    {
        return $query->where('office_id', $officeId);
    }

    /**
     * Idempotently register or renew a push token / subscription.
     */
    public static function registerSubscription(array $attributes): self
    {
        $endpoint = $attributes['endpoint'] ?? '';
        $hash = hash('sha256', (string) $endpoint);

        return static::updateOrCreate(
            ['token_hash' => $hash],
            [
                'user_id' => $attributes['user_id'],
                'office_id' => $attributes['office_id'] ?? null,
                'device_type' => $attributes['device_type'] ?? 'web_crm',
                'provider' => $attributes['provider'] ?? 'webpush',
                'endpoint' => $endpoint,
                'public_key' => $attributes['public_key'] ?? null,
                'auth_token' => $attributes['auth_token'] ?? null,
                'user_agent' => substr((string) ($attributes['user_agent'] ?? ''), 0, 255),
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );
    }

    /**
     * Deactivate a push subscription by its endpoint or token hash.
     */
    public static function deactivateSubscription(string $endpoint): bool
    {
        $hash = hash('sha256', $endpoint);

        return (bool) static::where('token_hash', $hash)->update(['is_active' => false]);
    }

    /**
     * Prune inactive subscriptions older than the specified threshold.
     */
    public static function pruneInactive(int $days = 30): int
    {
        return static::where('is_active', false)
            ->where('updated_at', '<', now()->subDays($days))
            ->delete();
    }
}
