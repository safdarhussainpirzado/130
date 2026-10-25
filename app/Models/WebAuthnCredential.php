<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebAuthnCredential extends Model
{
    protected $table = 'webauthn_credentials';

    protected $fillable = [
        'user_id',
        'credential_id',
        'public_key',
        'attestation_type',
        'sign_count',
        'user_verification',
        'device_name',
        'is_hardware_key',
        'revoked_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'sign_count'        => 'integer',
            'user_verification' => 'boolean',
            'is_hardware_key'   => 'boolean',
            'revoked_at'        => 'datetime',
            'last_used_at'      => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
