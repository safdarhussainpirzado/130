<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserScope extends Model
{
    protected $fillable = [
        'user_id',
        'office_id',
        'access_level',
        'label',
        'is_active',
    ];

    protected static function booted(): void
    {
        // A change of a user's operational assignment must not leave stale zone/beat context behind:
        // drop their cached filter options and bump security_version (forces re-login with the new scope).
        $refresh = function (UserScope $scope): void {
            $uid = (int) $scope->user_id;
            \Illuminate\Support\Facades\Cache::forget("crm:calls:filter_options:scope_{$uid}:v2");
            \Illuminate\Support\Facades\DB::table('users')->where('id', $uid)->increment('security_version');
        };
        static::saved($refresh);
        static::deleted($refresh);
    }

    protected $casts = [
        'is_active' => 'boolean',
        'office_id' => 'integer',
    ];

    /**
     * The access levels available per scope.
     */
    const ACCESS_LEVELS = [
        'read_only'  => 'Read Only',
        'read_write' => 'Read & Write',
        'full'       => 'Full Access',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * Returns human-readable display name for the scope.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->label) {
            return $this->label;
        }
        
        $office = $this->office;
        $typeName = $office ? ucfirst($office->type) : 'National';
        $unitName = $office ? $office->name : 'All Units';
        $level    = self::ACCESS_LEVELS[$this->access_level] ?? $this->access_level;

        return "{$typeName}: {$unitName} [{$level}]";
    }

    /**
     * Whether this scope allows write operations (store/update).
     */
    public function canWrite(): bool
    {
        if (isset($this->is_active) && !$this->is_active) {
            return false;
        }
        return in_array($this->access_level, ['read_write', 'full'], true);
    }

    /**
     * Whether this scope allows delete/export operations.
     */
    public function canDelete(): bool
    {
        if (isset($this->is_active) && !$this->is_active) {
            return false;
        }
        return $this->access_level === 'full';
    }
}
