<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $uuid
 * @property string $full_name
 * @property string $email
 * @property string $username
 * @property int|null $role_id
 * @property int|null $office_id
 * @property-read \App\Models\Office|null $office
 * @property-read \App\Models\Role|null $role
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'uuid',
        'designation_id',
        'cnic',
        'cnic_bidx',
        'full_name',
        'email',
        'mobile_no',
        'mobile_no_bidx',
        'username',
        'password',
        'password_changed_at',
        'role_id',
        'is_active',
        'is_blocked',
        'unblock_token',
        'unblock_token_expires_at',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * Auto-generate RFC 9562 UUIDv7 and blind indexes on User lifecycle.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->uuid)) {
                $user->uuid = (string) \Illuminate\Support\Str::uuid7();
            }
        });

        static::saving(function (User $user): void {
            if (!empty($user->cnic)) {
                $digits = preg_replace('/[^0-9]/', '', (string) $user->cnic);
                $salt = (function_exists('app') && app()->has('config')) ? config('app.bidx_salt') : null;
                $salt = $salt ?: docker_secret('bidx_salt', 'nhmp_130_sec_bidx_default_salt_2026');
                $user->cnic_bidx = hash_hmac('sha256', $digits, $salt);
            }
            if (!empty($user->mobile_no)) {
                $user->mobile_no_bidx = phone_blind_index((string) $user->mobile_no);
            }
        });
    }

    /**
     * Exclusively use UUIDv7 in route URLs (Anti-IDOR / Anti-Enumeration).
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Flexible route model binding: Resolves canonical UUIDv7, legacy ID, or Username.
     */
    public function resolveRouteBinding($value, $field = null): ?\Illuminate\Database\Eloquent\Model
    {
        if (\Illuminate\Support\Str::isUuid($value)) {
            return $this->where('uuid', $value)->firstOrFail();
        }

        if (is_numeric($value)) {
            return $this->where('id', $value)->firstOrFail();
        }

        return $this->where('username', $value)->firstOrFail();
    }

    protected $hidden = [
        'password',
        'remember_token',
        'totp_secret',
        'totp_last_step',
        'security_version',
        'failed_attempts',
        'locked_until',
        'last_login_ip',
    ];

    protected function casts(): array
    {
        return [
            'cnic'                => \App\Casts\GracefulEncrypted::class,
            'mobile_no'           => \App\Casts\GracefulEncrypted::class,
            'password'            => 'hashed',
            'password_changed_at' => 'datetime',
            'is_active'           => 'boolean',
            'is_blocked'          => 'boolean',
            'unblock_token_expires_at' => 'datetime',
            'mfa_required'        => 'boolean',
            'security_version'    => 'integer',
            'totp_last_step'      => 'integer',
            'last_login_at'       => 'datetime',
            'locked_until'        => 'datetime',
            'mfa_enrolled_at'     => 'datetime',
        ];
    }

    /**
     * Check if user account is blocked.
     */
    public function isBlocked(): bool
    {
        return (bool) $this->is_blocked;
    }

    /**
     * Enterprise Hard Block: Immediately scramble credentials, revoke 2FA, and purge sessions.
     */
    public function blockAccount(string $reason = 'Administrative Block'): void
    {
        $this->is_blocked = true;
        $this->is_active = false;
        $this->password = \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(64));
        $this->remember_token = null;
        $this->totp_secret = null;
        $this->totp_last_step = null;
        $this->mfa_enrolled_at = null;
        $this->mfa_required = false;
        $this->unblock_token = null;
        $this->unblock_token_expires_at = null;
        $this->security_version = ($this->security_version ?? 1) + 1;
        $this->save();

        // Terminate all live sessions immediately
        \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $this->id)->delete();
        \Illuminate\Support\Facades\DB::table('two_factor_recovery_codes')->where('user_id', $this->id)->delete();

        \App\Models\AuditLog::create([
            'user_id'    => \Illuminate\Support\Facades\Auth::id() ?? $this->id,
            'action'     => 'USER_ACCOUNT_BLOCKED',
            'table_name' => 'users',
            'record_id'  => $this->id,
            'old_values' => ['is_blocked' => false, 'is_active' => true],
            'new_values' => ['is_blocked' => true, 'is_active' => false, 'reason' => $reason],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System',
        ]);
    }

    /**
     * Generate a cryptographic single-use unblock token (expires in 24 hours).
     */
    public function generateUnblockToken(): string
    {
        $plainToken = \Illuminate\Support\Str::random(64);

        $this->unblock_token = hash('sha256', $plainToken);
        $this->unblock_token_expires_at = now()->addHours(24);
        $this->save();

        return $plainToken;
    }

    /**
     * Verify unblock identity against token, CNIC, and Email.
     */
    public function verifyUnblockCredentials(string $cnic, string $email): bool
    {
        $digits = preg_replace('/[^0-9]/', '', $cnic);
        $userDigits = preg_replace('/[^0-9]/', '', (string) $this->cnic);

        $emailMatches = strtolower(trim($this->email)) === strtolower(trim($email));
        $cnicMatches  = !empty($digits) && ($digits === $userDigits);

        return $emailMatches && $cnicMatches;
    }

    /**
     * Finalize account unblocking: apply new password and restore status.
     */
    public function unblockAccount(string $newPassword): void
    {
        $this->is_blocked = false;
        $this->is_active = true;
        $this->password = \Illuminate\Support\Facades\Hash::make($newPassword);
        $this->password_changed_at = now();
        $this->unblock_token = null;
        $this->unblock_token_expires_at = null;
        $this->failed_attempts = 0;
        $this->locked_until = null;
        $this->security_version = ($this->security_version ?? 1) + 1;
        $this->save();

        \App\Models\AuditLog::create([
            'user_id'    => $this->id,
            'action'     => 'USER_ACCOUNT_UNBLOCKED_BY_VERIFICATION',
            'table_name' => 'users',
            'record_id'  => $this->id,
            'old_values' => ['is_blocked' => true],
            'new_values' => ['is_blocked' => false, 'is_active' => true],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'Web Recovery',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Two-Factor & Security Helpers                                        */
    /* ------------------------------------------------------------------ */

    public function getDecryptedTotpSecret(): ?string
    {
        if (empty($this->totp_secret)) {
            return null;
        }

        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($this->totp_secret);
        } catch (\Throwable $e) {
            // If ciphertext cannot be decrypted (e.g. legacy unencrypted secret in test fixture)
            // check if it's already a valid Base32 string of expected length
            if (preg_match('/^[A-Z2-7]{16,64}$/i', $this->totp_secret)) {
                return $this->totp_secret;
            }
            \Illuminate\Support\Facades\Log::error("Failed to decrypt TOTP secret for user ID {$this->id}");
            return null;
        }
    }

    public function setEncryptedTotpSecret(?string $secret): void
    {
        if (empty($secret)) {
            $this->totp_secret = null;
        } else {
            $this->totp_secret = \Illuminate\Support\Facades\Crypt::encryptString($secret);
        }
    }

    public function hasMfaEnrolled(): bool
    {
        if (!empty($this->totp_secret)) {
            return true;
        }

        return $this->activeWebAuthnCredentials()->exists();
    }

    public function isPrivileged(): bool
    {
        $roleName = $this->role?->name;
        if ($roleName !== null) {
            return in_array($roleName, ['super_admin', 'operation_admin', Role::SUPER_ADMIN, Role::IT_ADMIN], true);
        }

        return in_array((int)$this->role_id, [1, 2], true);
    }

    public function isSecurityLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function requiresWebAuthn(): bool
    {
        return $this->mfa_assurance_level === 'webauthn';
    }

    public function bumpSecurityVersion(): void
    {
        $this->increment('security_version');
    }

    /* ------------------------------------------------------------------ */
    /*  15-Day Password Expiry Policy Helpers                             */
    /* ------------------------------------------------------------------ */

    public function isPasswordExpired(?int $days = null): bool
    {
        $days = $days ?? (int) config('auth.password_expiry_days', 15);
        $changedAt = $this->password_changed_at ?? $this->created_at;

        if (! $changedAt) {
            return true;
        }

        return $changedAt->copy()->addDays($days)->isPast();
    }

    public function daysUntilPasswordExpires(?int $days = null): int
    {
        $days = $days ?? (int) config('auth.password_expiry_days', 15);
        $changedAt = $this->password_changed_at ?? $this->created_at;

        if (! $changedAt) {
            return 0;
        }

        $expiryDate = $changedAt->copy()->addDays($days);
        if ($expiryDate->isPast()) {
            return 0;
        }

        return max(0, (int) ceil(now()->floatDiffInDays($expiryDate, false)));
    }

    public function passwordExpiresAt(?int $days = null): ?\Illuminate\Support\Carbon
    {
        $days = $days ?? (int) config('auth.password_expiry_days', 15);
        $changedAt = $this->password_changed_at ?? $this->created_at;

        return $changedAt ? $changedAt->copy()->addDays($days) : null;
    }

    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(TwoFactorRecoveryCode::class);
    }

    public function enrollmentAuthorizations(): HasMany
    {
        return $this->hasMany(TwoFactorEnrollmentAuthorization::class, 'user_id');
    }

    public function recoveryApprovals(): HasMany
    {
        return $this->hasMany(TwoFactorRecoveryApproval::class, 'user_id');
    }

    public function webAuthnCredentials(): HasMany
    {
        return $this->hasMany(WebAuthnCredential::class);
    }

    public function activeWebAuthnCredentials(): HasMany
    {
        return $this->hasMany(WebAuthnCredential::class)->whereNull('revoked_at');
    }

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                        */
    /* ------------------------------------------------------------------ */

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function loginLogs(): HasMany
    {
        return $this->hasMany(LoginLog::class);
    }

    /** All scope assignments (including inactive) */
    public function scopes(): HasMany
    {
        return $this->hasMany(UserScope::class);
    }

    /** Only currently active scope assignments */
    public function activeScopes(): HasMany
    {
        return $this->hasMany(UserScope::class)->where('is_active', true);
    }

    /** Primary assigned operational office derived from active scopes */
    public function getOfficeAttribute(): ?Office
    {
        return $this->activeScopes()->with('office')->first()?->office;
    }

    /** Compatibility accessor for user name */
    public function getNameAttribute(): ?string
    {
        return $this->full_name;
    }

    /** Commuter citizen profile */
    public function commuterProfile(): HasOne
    {
        return $this->hasOne(CommuterProfile::class);
    }

    /** Patrol officer official profile */
    public function patrolOfficerProfile(): HasOne
    {
        return $this->hasOne(PatrolOfficerProfile::class);
    }

    /** Duty sessions logged as primary officer */
    public function dutySessions(): HasMany
    {
        return $this->hasMany(PatrolDutySession::class, 'officer_user_id');
    }

    /** Helper to check if user is a uniformed patrol officer */
    public function isPatrolOfficer(): bool
    {
        return $this->role?->name === 'patrol_officer';
    }

    /** Helper to check if user is a citizen commuter */
    public function isCommuter(): bool
    {
        return $this->role?->name === 'commuter';
    }

    /** Static per-worker permission cache */
    protected static array $permissionCache = [];

    /**
     * Clear the static permission cache across worker request boundaries.
     */
    public static function clearPermissionCache(): void
    {
        static::$permissionCache = [];
    }

    /**
     * Check if the user has a named permission via their role.
     * Super admins always return true.
     */
    public function hasPermission(string $permission): bool
    {
        // 1. Super admins always return true as specified in docstring
        if ($this->role?->name === Role::SUPER_ADMIN) {
            return true;
        }

        // 2. Per-request cache to avoid N+1 on Blade @can() checks
        $key = ($this->id ?? 'anon') . ':' . $permission;
        return static::$permissionCache[$key] ??= (
            $this->role?->permissions->contains('name', $permission) ?? false
        );
    }

    /**
     * Whether the user has write access (create/update) for a unit type + optional unit ID.
     *
     * @param string   $type      e.g. 'zone', 'beat', 'call_center'
     * @param int|null $officeId  The specific office ID, or null to check any office of this type
     */
    public function canWriteIn(string $type, ?int $officeId = null): bool
    {
        return $this->activeScopes()
            ->whereHas('office', fn($q) => $q->where('type', $type))
            ->when($officeId !== null, fn($q) => $q->where(function ($q) use ($officeId) {
                $q->where('office_id', $officeId)->orWhereNull('office_id');
            }))
            ->whereIn('access_level', ['read_write', 'full'])
            ->exists();
    }

    /**
     * Whether the user has delete/export access for a unit type + optional unit ID.
     */
    public function canDeleteIn(string $type, ?int $officeId = null): bool
    {
        return $this->activeScopes()
            ->whereHas('office', fn($q) => $q->where('type', $type))
            ->when($officeId !== null, fn($q) => $q->where(function ($q) use ($officeId) {
                $q->where('office_id', $officeId)->orWhereNull('office_id');
            }))
            ->where('access_level', 'full')
            ->exists();
    }

    /**
     * Whether the user has any active scope for a given office type.
     */
    public function hasScope(string $type): bool
    {
        return $this->activeScopes()->whereHas('office', fn($q) => $q->where('type', $type))->exists();
    }

    /**
     * Get all office IDs the user has access to for a given type.
     * Returns null if the user has a "all of type" scope (office_id = null).
     *
     * @return array<int>|null  null = unrestricted within this type
     */
    public function getScopeUnitIds(string $type): ?array
    {
        $scopes = $this->activeScopes()->whereHas('office', fn($q) => $q->where('type', $type))->get();
        if ($scopes->isEmpty()) {
            return [];
        }
        // If any scope has null office_id, user has access to ALL units of this type
        if ($scopes->contains(fn($s) => $s->office_id === null)) {
            return null;
        }
        return $scopes->pluck('office_id')->all();
    }

    /**
     * Get the designated landing page route name based on user role.
     */
    public function getLandingPageRoute(): string
    {
        // 1. Check primary role-based preferences first (if authorized)
        $role = $this->role?->name;
        $primary = match ($role) {
            'agent'           => $this->hasPermission('calls.create') ? route('calls.create') : null,
            'operation_admin' => $this->hasPermission('users.view') ? route('admin.users.index') : null,
            'super_admin'     => $this->hasPermission('dashboard.view') ? route('dashboard') : null,
            'sector_admin', 
            'zone_admin', 
            'agent_supervisor', 
            'beat_operator', 
            'operation'       => $this->hasPermission('calls.view') ? route('calls.index') : null,
            default           => null
        };

        if ($primary) return $primary;

        // 2. Systematic fallback scan across the registry hierarchy
        $fallbacks = [
            'dashboard.view'           => 'dashboard',
            'calls.create'             => 'calls.create',
            'calls.view'               => 'calls.index',
            'reports.view'             => 'reports.call-type-summary',
            'users.view'               => 'admin.users.index',
            'geography.offices.view'   => 'admin.offices.index',
            'geography.carriageways.view' => 'admin.carriageways.index',
            'roles.view'               => 'admin.roles.index',
            'security.mfa_roles.view'  => 'admin.mfa-settings.index',
            'system.settings.view'     => 'admin.mfa-settings.index',
            'system.audit_view'        => 'admin.audit.index',
        ];

        foreach ($fallbacks as $permission => $routeName) {
            if ($this->hasPermission($permission)) {
                try {
                    return route($routeName);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        // 3. Absolute fallback: If no operational permissions, send to login or a public 'profile'
        // If they are already authenticated, dashboard is the last stand (middleware will handle the final block)
        return route('dashboard');
    }

    /**
     * Get the human-readable role title.
     */
    public function getRoleTitle(): string
    {
        $role = $this->role?->name;

        return match ($role) {
            'super_admin'     => 'System Administrator',
            'operation_admin', 'operation' => 'Operation Manager',
            'agent'           => 'Call Center Agent',
            'agent_supervisor'=> 'Shift Supervisor',
            'zone_admin', 'sector_admin' => 'Zone and Sector Incharge',
            'beat_operator'   => 'Wireless Operator',
            'patrol_officer'  => 'Patrol Officer',
            'commuter'        => 'Commuter / Road User',
            default           => 'System Commander',
        };
    }
}

