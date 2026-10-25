<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfaRolePolicy extends Model
{
    protected $table = 'mfa_role_policies';

    protected $fillable = [
        'role_id',
        'mfa_required',
        'scheduled_re_enable_at',
        'reason',
        'updated_by',
    ];

    protected $casts = [
        'mfa_required'           => 'boolean',
        'scheduled_re_enable_at' => 'datetime',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Determine if MFA is currently enforced, taking into account scheduled re-enablement.
     */
    public function isEnforcedNow(): bool
    {
        if (!$this->mfa_required) {
            if ($this->scheduled_re_enable_at && now()->greaterThanOrEqualTo($this->scheduled_re_enable_at)) {
                return true;
            }
            return false;
        }

        return true;
    }
}
