<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserShowResource extends JsonResource
{
    /**
     * Transform the resource into an array for authorized single User Show/Profile.
     * Enforces strict data minimization: zero password hashes, zero TOTP secrets,
     * zero recovery codes.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'mobile_no' => $this->mobile_no,
            'cnic' => $this->cnic,
            'is_active' => (bool) $this->is_active,
            'role_id' => $this->role_id,
            'role' => $this->role ? [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'display_name' => $this->role->display_name,
                'scope_level' => $this->role->scope_level,
            ] : null,
            'designation_id' => $this->designation_id,
            'designation' => $this->designation ? [
                'id' => $this->designation->id,
                'name' => $this->designation->name,
                'short_code' => $this->designation->short_code,
                'bps' => $this->designation->bps,
            ] : null,
            'active_scopes' => $this->activeScopes ? $this->activeScopes->map(fn($s) => [
                'id' => $s->id,
                'office_id' => $s->office_id,
                'access_level' => $s->access_level,
                'office' => $s->office ? [
                    'id' => $s->office->id,
                    'name' => $s->office->name,
                    'type' => $s->office->type,
                ] : null,
            ]) : [],
            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toISOString() : null,
        ];
    }
}
