<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorRecoveryApproval extends Model
{
    public $timestamps = false;

    protected $table = 'two_factor_recovery_approvals';

    protected $fillable = [
        'user_id',
        'request_token_hash',
        'reason',
        'target_role_id',
        'approver1_id',
        'approver1_at',
        'approver2_id',
        'approver2_at',
        'status',
        'rejection_reason',
        'expires_at',
        'consumed_at',
        'created_at',
    ];

    protected $hidden = [
        'request_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'approver1_at' => 'datetime',
            'approver2_at' => 'datetime',
            'expires_at'   => 'datetime',
            'consumed_at'  => 'datetime',
            'created_at'   => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function targetRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'target_role_id');
    }

    public function approver1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver1_id');
    }

    public function approver2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver2_id');
    }

    public function isPrivileged(): bool
    {
        return in_array($this->target_role_id, [1, 2]) || ($this->user?->isPrivileged() ?? false);
    }

    public function requiresDualControl(): bool
    {
        return $this->isPrivileged();
    }

    public function isFullyApproved(): bool
    {
        if ($this->status !== 'approved') {
            return false;
        }

        if ($this->isPrivileged()) {
            return $this->approver1_id !== null && $this->approver2_id !== null && $this->approver1_id !== $this->approver2_id;
        }

        return $this->approver1_id !== null;
    }

    public function isValidForConsumption(): bool
    {
        return $this->status === 'approved' &&
               $this->consumed_at === null &&
               $this->expires_at->isFuture() &&
               $this->isFullyApproved();
    }
}
