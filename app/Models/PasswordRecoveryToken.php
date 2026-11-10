<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PasswordRecoveryToken extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'support_request_id',
        'user_id',
        'token_hash',
        'expires_at',
        'used_at',
        'created_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PasswordRecoveryToken $model): void {
            if (empty($model->created_at)) {
                $model->created_at = \Illuminate\Support\Carbon::now();
            }
        });
    }

    public function supportRequest(): BelongsTo
    {
        return $this->belongsTo(SupportRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
    }

    /**
     * Generate a high-entropy 64-character hex token, store SHA-256 hash, and return plaintext token once.
     *
     * @return array{plain_token: string, record: self}
     */
    public static function createSecureToken(SupportRequest $supportRequest, User $user, int $lifetimeMinutes = 60): array
    {
        $plainToken = bin2hex(random_bytes(32)); // 64 hex characters (256 bits of CSPRNG entropy)
        $tokenHash = hash('sha256', $plainToken);

        $record = static::create([
            'support_request_id' => $supportRequest->id,
            'user_id'            => $user->id,
            'token_hash'         => $tokenHash,
            'expires_at'         => now()->addMinutes($lifetimeMinutes),
        ]);

        return [
            'plain_token' => $plainToken,
            'record'      => $record,
        ];
    }

    /**
     * Verify a presented plaintext token against SHA-256 hash.
     * Prevents timing attacks with hash_equals.
     */
    public static function findValidToken(string $plainToken): ?self
    {
        $plainToken = trim($plainToken);
        if (strlen($plainToken) !== 64 || !ctype_xdigit($plainToken)) {
            return null;
        }

        $hash = hash('sha256', $plainToken);

        /** @var self|null $record */
        $record = static::with(['user', 'supportRequest'])
            ->where('token_hash', $hash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        return $record;
    }

    /**
     * Atomically mark this token as consumed.
     */
    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }
}
