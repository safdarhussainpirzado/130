<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorRecoveryCode extends Model
{
    public $timestamps = false;

    protected $table = 'two_factor_recovery_codes';

    protected $fillable = [
        'user_id',
        'code_hash',
        'generation_id',
        'consumed_at',
        'created_at',
    ];

    protected $hidden = [
        'code_hash',
    ];

    protected function casts(): array
    {
        return [
            'consumed_at' => 'datetime',
            'created_at'  => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
