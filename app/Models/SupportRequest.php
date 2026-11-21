<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $requester_id
 * @property string $request_type
 * @property string|null $category
 * @property string $subject
 * @property string $description
 * @property string $current_status
 * @property string $priority
 * @property int|null $source_office_id
 * @property int|null $destination_office_id
 * @property int|null $assigned_approver_id
 * @property int|null $assigned_role_id
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SupportRequest extends Model
{
    use HasFactory;

    // Request Types
    public const TYPE_PASSWORD_RECOVERY = 'password_recovery';
    public const TYPE_MFA_SUPPORT       = 'mfa_support';
    public const TYPE_ACCOUNT_BLOCK     = 'account_block';
    public const TYPE_ORG_TRANSFER      = 'org_transfer';
    public const TYPE_OTHER_ACCOUNT     = 'other_account';

    // Status State Machine
    public const STATUS_SUBMITTED                         = 'submitted';
    public const STATUS_PENDING_SOURCE_APPROVAL           = 'pending_source_approval';
    public const STATUS_PENDING_SOURCE_ZONE_APPROVAL      = 'pending_source_zone_approval';
    public const STATUS_PENDING_NATIONAL_REVIEW           = 'pending_national_review';
    public const STATUS_PENDING_DESTINATION_ZONE_APPROVAL = 'pending_destination_zone_approval';
    public const STATUS_PENDING_DESTINATION_SECTOR_APPROVAL = 'pending_destination_sector_approval';
    public const STATUS_PENDING_DESTINATION_CONFIRMATION  = 'pending_destination_confirmation';
    public const STATUS_PENDING_MANAGER_REVIEW            = 'pending_manager_review';
    public const STATUS_APPROVED                          = 'approved';
    public const STATUS_REJECTED                          = 'rejected';
    public const STATUS_COMPLETED                         = 'completed';
    public const STATUS_CANCELLED                         = 'cancelled';
    public const STATUS_EXPIRED                           = 'expired';

    // Priorities
    public const PRIORITY_LOW    = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH   = 'high';
    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'public_id',
        'requester_id',
        'request_type',
        'category',
        'subject',
        'description',
        'current_status',
        'priority',
        'source_office_id',
        'destination_office_id',
        'assigned_approver_id',
        'assigned_role_id',
        'metadata',
        'submitted_at',
        'completed_at',
    ];

    protected $casts = [
        'metadata'     => 'array',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Boot model: Auto-generate cryptographically secure RFC 4122 UUIDv4 on creation.
     */
    protected static function booted(): void
    {
        static::creating(function (SupportRequest $request): void {
            if (empty($request->public_id)) {
                $request->public_id = (string) Str::uuid();
            }
            if (empty($request->submitted_at)) {
                $request->submitted_at = now();
            }
        });

        static::created(function (SupportRequest $request): void {
            \App\Services\Notifications\PushDispatcher::dispatchSupport($request, 'created');
        });
    }

    /**
     * Route model binding uses opaque public UUIDv4.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Flexible route resolution: resolves canonical UUIDv4 or legacy integer PK for tests.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if (Str::isUuid($value)) {
            return $this->where('public_id', $value)->firstOrFail();
        }

        if (is_numeric($value)) {
            return $this->where('id', $value)->firstOrFail();
        }

        return $this->where('public_id', $value)->firstOrFail();
    }

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                     */
    /* ------------------------------------------------------------------ */

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function sourceOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'source_office_id');
    }

    public function destinationOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'destination_office_id');
    }

    public function assignedApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_approver_id');
    }

    public function assignedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'assigned_role_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(SupportRequestAction::class)->orderBy('created_at', 'asc');
    }

    public function recoveryTokens(): HasMany
    {
        return $this->hasMany(PasswordRecoveryToken::class);
    }

    public function activeRecoveryToken(): ?PasswordRecoveryToken
    {
        return $this->recoveryTokens()
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    public function assignmentHistories(): HasMany
    {
        return $this->hasMany(UserAssignmentHistory::class, 'transfer_request_id');
    }

    /* ------------------------------------------------------------------ */
    /*  Query Scopes                                                      */
    /* ------------------------------------------------------------------ */

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('current_status', [
            self::STATUS_SUBMITTED,
            self::STATUS_PENDING_SOURCE_APPROVAL,
            self::STATUS_PENDING_SOURCE_ZONE_APPROVAL,
            self::STATUS_PENDING_NATIONAL_REVIEW,
            self::STATUS_PENDING_DESTINATION_ZONE_APPROVAL,
            self::STATUS_PENDING_DESTINATION_SECTOR_APPROVAL,
            self::STATUS_PENDING_DESTINATION_CONFIRMATION,
            self::STATUS_PENDING_MANAGER_REVIEW,
        ]);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('current_status', self::STATUS_COMPLETED);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('current_status', self::STATUS_REJECTED);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('requester_id', $userId);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('request_type', $type);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('current_status', $status);
    }

    /* ------------------------------------------------------------------ */
    /*  Status & State Helpers                                            */
    /* ------------------------------------------------------------------ */

    public function isPending(): bool
    {
        return in_array($this->current_status, [
            self::STATUS_SUBMITTED,
            self::STATUS_PENDING_SOURCE_APPROVAL,
            self::STATUS_PENDING_DESTINATION_CONFIRMATION,
            self::STATUS_PENDING_MANAGER_REVIEW,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->current_status, [
            self::STATUS_COMPLETED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
        ], true);
    }

    public function canBeCancelledBy(User $user): bool
    {
        if ($this->requester_id !== $user->id) {
            return false;
        }

        return $this->isPending();
    }

    public function isTransfer(): bool
    {
        return $this->request_type === self::TYPE_ORG_TRANSFER;
    }

    public function isPasswordRecovery(): bool
    {
        return $this->request_type === self::TYPE_PASSWORD_RECOVERY;
    }

    public function isAccountBlock(): bool
    {
        return $this->request_type === self::TYPE_ACCOUNT_BLOCK;
    }

    public function isMfaSupport(): bool
    {
        return $this->request_type === self::TYPE_MFA_SUPPORT;
    }

    /* ------------------------------------------------------------------ */
    /*  Presentation Helpers (Badges & Human-readable Labels)             */
    /* ------------------------------------------------------------------ */

    public function getStatusLabel(): string
    {
        return match ($this->current_status) {
            self::STATUS_SUBMITTED                         => 'Submitted',
            self::STATUS_PENDING_SOURCE_APPROVAL           => 'Awaiting Source Departure',
            self::STATUS_PENDING_SOURCE_ZONE_APPROVAL      => 'Awaiting Zone Clearance',
            self::STATUS_PENDING_NATIONAL_REVIEW           => 'Awaiting HQ Endorsement',
            self::STATUS_PENDING_DESTINATION_ZONE_APPROVAL => 'Awaiting Dest Zone Clearance',
            self::STATUS_PENDING_DESTINATION_SECTOR_APPROVAL => 'Awaiting Dest Sector Clearance',
            self::STATUS_PENDING_DESTINATION_CONFIRMATION  => 'Awaiting Destination Arrival',
            self::STATUS_PENDING_MANAGER_REVIEW            => 'Awaiting Operation Manager',
            self::STATUS_APPROVED                          => 'Approved',
            self::STATUS_REJECTED                          => 'Rejected',
            self::STATUS_COMPLETED                         => 'Completed',
            self::STATUS_CANCELLED                         => 'Cancelled',
            self::STATUS_EXPIRED                           => 'Expired',
            default                                        => ucfirst(str_replace('_', ' ', $this->current_status)),
        };
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->current_status) {
            self::STATUS_SUBMITTED                         => 'bg-blue-50 text-blue-700 border-blue-200',
            self::STATUS_PENDING_SOURCE_APPROVAL           => 'bg-amber-50 text-amber-700 border-amber-200',
            self::STATUS_PENDING_SOURCE_ZONE_APPROVAL      => 'bg-purple-50 text-purple-700 border-purple-200',
            self::STATUS_PENDING_NATIONAL_REVIEW           => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            self::STATUS_PENDING_DESTINATION_ZONE_APPROVAL => 'bg-violet-50 text-violet-700 border-violet-200',
            self::STATUS_PENDING_DESTINATION_SECTOR_APPROVAL => 'bg-cyan-50 text-cyan-700 border-cyan-200',
            self::STATUS_PENDING_DESTINATION_CONFIRMATION  => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::STATUS_PENDING_MANAGER_REVIEW            => 'bg-purple-50 text-purple-700 border-purple-200',
            self::STATUS_APPROVED                          => 'bg-teal-50 text-teal-700 border-teal-200',
            self::STATUS_COMPLETED                         => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::STATUS_REJECTED                          => 'bg-rose-50 text-rose-700 border-rose-200',
            self::STATUS_CANCELLED                         => 'bg-slate-100 text-slate-600 border-slate-200',
            self::STATUS_EXPIRED                           => 'bg-zinc-100 text-zinc-600 border-zinc-200',
            default                                        => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    }

    public function getTypeLabel(): string
    {
        return match ($this->request_type) {
            self::TYPE_PASSWORD_RECOVERY => 'Password Recovery',
            self::TYPE_MFA_SUPPORT       => 'MFA / Authenticator Assistance',
            self::TYPE_ACCOUNT_BLOCK     => 'Account Blocking',
            self::TYPE_ORG_TRANSFER      => 'Organizational Transfer',
            self::TYPE_OTHER_ACCOUNT     => 'Other Account Issue',
            default                      => ucwords(str_replace('_', ' ', $this->request_type)),
        };
    }

    public function getTypeBadgeClass(): string
    {
        return match ($this->request_type) {
            self::TYPE_PASSWORD_RECOVERY => 'bg-amber-50 text-amber-700 border-amber-200',
            self::TYPE_MFA_SUPPORT       => 'bg-cyan-50 text-cyan-700 border-cyan-200',
            self::TYPE_ACCOUNT_BLOCK     => 'bg-rose-50 text-rose-700 border-rose-200',
            self::TYPE_ORG_TRANSFER      => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::TYPE_OTHER_ACCOUNT     => 'bg-slate-50 text-slate-700 border-slate-200',
            default                      => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    }

    public function getPriorityBadgeClass(): string
    {
        return match ($this->priority) {
            self::PRIORITY_URGENT => 'bg-rose-100 text-rose-800 border-rose-300 font-bold',
            self::PRIORITY_HIGH   => 'bg-amber-100 text-amber-800 border-amber-300 font-semibold',
            self::PRIORITY_NORMAL => 'bg-blue-50 text-blue-700 border-blue-200',
            self::PRIORITY_LOW    => 'bg-slate-100 text-slate-600 border-slate-200',
            default               => 'bg-slate-100 text-slate-600 border-slate-200',
        };
    }
}
