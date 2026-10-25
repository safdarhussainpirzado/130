<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    // Role name constants — reference these instead of raw strings
    const SUPER_ADMIN      = 'super_admin';
    const IT_ADMIN         = 'operation_admin';
    const ZONE_ADMIN       = 'zone_admin';
    const SECTOR_ADMIN     = 'sector_admin';
    const BEAT_OPERATOR    = 'beat_operator';
    const AGENT_SUPERVISOR = 'agent_supervisor';
    const AGENT            = 'agent';

    public $timestamps = true;

    protected $fillable = ['name', 'display_name', 'scope_level', 'status'];

    protected $appends = ['is_mfa_enforced', 'is_mfa_protected'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function mfaPolicy()
    {
        return $this->hasOne(MfaRolePolicy::class);
    }

    public function getIsMfaEnforcedAttribute(): bool
    {
        if (in_array($this->name, [self::SUPER_ADMIN, self::IT_ADMIN])) {
            return true;
        }

        $policy = $this->relationLoaded('mfaPolicy') ? $this->mfaPolicy : $this->mfaPolicy()->first();

        return $policy ? $policy->isEnforcedNow() : true;
    }

    public function getIsMfaProtectedAttribute(): bool
    {
        return in_array($this->name, [self::SUPER_ADMIN, self::IT_ADMIN]);
    }
}
