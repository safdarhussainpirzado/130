<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserListResource extends JsonResource
{
    /**
     * Transform the resource into an array for User Management.
     * Enforces strict data minimization: zero credentials, zero MFA secrets,
     * zero failed login telemetry.
     */
    public function toArray(Request $request): array
    {
        $scopes = $this->relationLoaded('activeScopes')
            ? $this->activeScopes
            : ($this->relationLoaded('scopes') ? $this->scopes : collect([]));

        $primaryScope = $scopes?->first();
        $office = ($primaryScope && $primaryScope->relationLoaded('office')) ? $primaryScope->office : null;

        $sector = ($office && $office->type === 'beat' && $office->relationLoaded('parent')) ? $office->parent : ($office && $office->type === 'sector' ? $office : null);
        $zone = ($sector && $sector->relationLoaded('parent')) ? $sector->parent : ($office && $office->type === 'zone' ? $office : null);

        $role = $this->relationLoaded('role') ? $this->role : null;
        $designation = $this->relationLoaded('designation') ? $this->designation : null;

        if ($office) {
            $scopeUnitName = $office->name;
            $scopeUnitTypeRaw = strtolower($office->type);
            $scopeUnitType = ucfirst($office->type);
        } elseif ($role && ($role->scope_level === 'call_centre' || in_array($role->name, ['agent', 'agent_supervisor'], true))) {
            $scopeUnitName = 'Helpline 130';
            $scopeUnitTypeRaw = 'call_centre';
            $scopeUnitType = 'Call Center';
        } else {
            $scopeUnitName = 'National';
            $scopeUnitTypeRaw = 'national';
            $scopeUnitType = 'National';
        }

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'username' => $this->username,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'mobile_no' => $this->mobile_no,
            'cnic' => $this->cnic,
            'is_active' => (bool) $this->is_active,
            'is_blocked' => (bool) $this->is_blocked,
            'is_security_locked' => (bool) ($this->locked_until && $this->locked_until->isFuture()),
            'status_state' => $this->is_blocked ? 'blocked' : (($this->locked_until && $this->locked_until->isFuture()) ? 'security_locked' : ($this->is_active ? 'active' : 'suspended')),
            'status_label' => $this->is_blocked ? 'Blocked' : (($this->locked_until && $this->locked_until->isFuture()) ? 'Security Locked' : ($this->is_active ? 'Active' : 'Suspended')),
            'mfa_required' => (bool) $this->mfa_required,
            'mfa_enrolled_at' => $this->mfa_enrolled_at ? $this->mfa_enrolled_at->toISOString() : null,
            'last_login_at' => $this->last_login_at ? $this->last_login_at->toISOString() : null,
            'role_id' => $this->role_id,
            'role_name' => $role?->name,
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
            ] : null,
            'designation_id' => $this->designation_id,
            'designation_title' => $designation?->name,
            'designation' => $designation ? [
                'id' => $designation->id,
                'name' => $designation->name,
                'short_code' => $designation->short_code,
                'bps' => $designation->bps,
            ] : null,
            'active_scopes' => ($scopes && $scopes->isNotEmpty()) ? $scopes->map(fn($s) => [
                'id' => $s->id,
                'office_id' => $s->office_id,
                'access_level' => $s->access_level,
                'label' => $s->label,
                'is_active' => (bool) $s->is_active,
                'office' => ($s->relationLoaded('office') && $s->office) ? [
                    'id' => $s->office->id,
                    'name' => $s->office->name,
                    'type' => $s->office->type,
                    'parent_id' => $s->office->parent_id,
                ] : null,
            ]) : [],
            'scope_unit_id' => $primaryScope?->office_id,
            'scope_unit_name' => $scopeUnitName,
            'scope_unit_type' => $scopeUnitType,
            'scope_unit_type_raw' => $scopeUnitTypeRaw,
            'scope_summary' => $scopeUnitName . ($scopeUnitType ? " ({$scopeUnitType})" : ''),
            'parent_sector_id' => $sector?->id,
            'parent_zone_id' => $zone?->id,
            'patrol_profile' => ($this->relationLoaded('patrolOfficerProfile') && $this->patrolOfficerProfile) ? [
                'badge_number' => $this->patrolOfficerProfile->badge_number,
                'is_on_duty' => (bool) $this->patrolOfficerProfile->is_on_duty,
                'assigned_beat_id' => $this->patrolOfficerProfile->assigned_beat_id,
            ] : null,
            'commuter_profile' => ($this->relationLoaded('commuterProfile') && $this->commuterProfile) ? [
                'gender' => $this->commuterProfile->gender,
                'is_cnic_verified' => (bool) $this->commuterProfile->is_cnic_verified,
            ] : null,
        ];
    }
}
