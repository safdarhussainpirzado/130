<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Call extends Model
{
    use HasFactory;

    public const PRIORITY_P1 = 1;
    public const PRIORITY_P2 = 2;
    public const PRIORITY_P3 = 3;
    public const PRIORITY_P4 = 4;
    public const PRIORITY_P5 = 5;

    protected $fillable = [
        'uuid',
        'call_number',
        'agent_id',
        'caller_number',
        'caller_number_bidx',
        'caller_name',
        'caller_gender',
        'is_reminder_call',
        'parent_call_id',
        'call_type_id',
        'call_sub_type_id',
        'details',
        'language_id',
        'is_phone_activity',
        'vehicle_type_id',
        'vehicle_no',
        'carriageway_id',
        'km_marker_text',
        'office_id',
        'caller_lat',
        'caller_lng',
        'status',
        'priority',
        'forwarded_to_level',
        'forwarded_to_user_id',
        'rating',
        'call_start_time',
        'call_pickup_time',
        'call_end_time',
        'wait_time_seconds',
        'agent_call_duration',
        'response_time_sec',
        'resolution_time_sec',
        'pending_remarks',
        'pending_status_by',
        'inprogress_remarks',
        'inprogress_status_by',
        'inprogress_at',
        'tiger_reached_remarks',
        'tiger_reached_status_by',
        'tiger_reached_at',
        'completed_remarks',
        'completed_status_by',
        'completed_at',
        'cancelled_remarks',
        'tiger_response_time_sec',
        'assistance_duration_sec',
        'tiger_id',
        'patrol_officer_id',
        'followup_needed',
        'call_reminder_count',
        'last_reminder_at',
        'location_details',
        'commuter_user_id',
        'patrol_duty_session_id',
        'assigned_at',
        'acknowledged_at',
        'acknowledged_by',
        'cannot_respond_reason',
        'public_summary',
        'internal_control_note',
        'completion_outcome',
    ];

    protected $casts = [
        'caller_number'     => \App\Casts\GracefulEncrypted::class,
        'caller_name'       => \App\Casts\GracefulEncrypted::class,
        'is_reminder_call'  => 'boolean',
        'is_phone_activity' => 'boolean',
        'followup_needed'   => 'boolean',
        'call_start_time'   => 'datetime',
        'call_pickup_time'  => 'datetime',
        'call_end_time'     => 'datetime',
        'assigned_at'       => 'datetime',
        'acknowledged_at'   => 'datetime',
        'inprogress_at'     => 'datetime',
        'tiger_reached_at'  => 'datetime',
        'completed_at'      => 'datetime',
        'last_reminder_at'  => 'datetime',
    ];

    /**
     * Auto-generate RFC 9562 UUIDv7 and Blind Index on Call saving.
     */
    protected static function booted(): void
    {
        static::creating(function (Call $call): void {
            if (empty($call->uuid)) {
                $call->uuid = (string) \Illuminate\Support\Str::uuid7();
            }
        });

        static::saving(function (Call $call): void {
            if (!empty($call->caller_number)) {
                $call->caller_number_bidx = phone_blind_index((string) $call->caller_number);
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
     * Canonical UUIDv7 route-model binding with backward-compatible numeric ID resolution.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if (\Illuminate\Support\Str::isUuid($value)) {
            return $this->where('uuid', $value)->firstOrFail();
        }

        if (is_numeric($value)) {
            return $this->where('id', (int) $value)->firstOrFail();
        }

        abort(404, 'Call record not found.');
    }

    public function isP1(): bool
    {
        return (int) $this->priority === self::PRIORITY_P1;
    }

    public function isP1OrP2(): bool
    {
        $p = (int) $this->priority;

        return $p === self::PRIORITY_P1 || $p === self::PRIORITY_P2;
    }

    public function getPriorityLabel(): string
    {
        $p = (int) $this->priority;

        return match ($p) {
            1 => 'P1',
            2 => 'P2',
            3 => 'P3',
            4 => 'P4',
            5 => 'P5',
            default => 'P'.($p ?: 3),
        };
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function tiger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tiger_id');
    }

    public function tigerReachedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tiger_reached_status_by');
    }

    public function inprogressStatusBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inprogress_status_by');
    }

    public function completedStatusBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_status_by');
    }

    public function callType(): BelongsTo
    {
        return $this->belongsTo(CallType::class);
    }

    public function callSubType(): BelongsTo
    {
        return $this->belongsTo(CallSubType::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function carriageway(): BelongsTo
    {
        return $this->belongsTo(Carriageway::class);
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }


    public function statusHistory(): HasMany
    {
        return $this->hasMany(CallStatusHistory::class);
    }

    public function outgoingCalls(): HasMany
    {
        return $this->hasMany(OutgoingCall::class);
    }

    public function commuterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commuter_user_id');
    }

    public function patrolDutySession(): BelongsTo
    {
        return $this->belongsTo(PatrolDutySession::class, 'patrol_duty_session_id');
    }

    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CaseMessage::class, 'call_id')->orderBy('created_at', 'asc');
    }

    /**
     * Get computed Tiger Response Time in seconds: Tiger Reached Time - Help Initiated Time
     */
    public function getComputedTigerResponseTimeSecAttribute(): ?int
    {
        if ($this->tiger_response_time_sec !== null) {
            return (int) $this->tiger_response_time_sec;
        }

        if ($this->tiger_reached_at && $this->inprogress_at) {
            return max(0, (int) $this->inprogress_at->diffInSeconds($this->tiger_reached_at));
        }

        return null;
    }

    /**
     * Get computed Assistance Duration in seconds: Help Completed Time - Tiger Reached Time
     */
    public function getComputedAssistanceDurationSecAttribute(): ?int
    {
        if ($this->assistance_duration_sec !== null) {
            return (int) $this->assistance_duration_sec;
        }

        if ($this->completed_at && $this->tiger_reached_at) {
            return max(0, (int) $this->tiger_reached_at->diffInSeconds($this->completed_at));
        }

        return null;
    }

    /**
     * Get computed Total Help Resolution Time in seconds: Help Completed Time - Help Created Time
     */
    public function getComputedResolutionTimeSecAttribute(): ?int
    {
        if ($this->resolution_time_sec !== null) {
            return (int) $this->resolution_time_sec;
        }

        $startTime = $this->created_at ?? $this->call_start_time;
        if ($this->completed_at && $startTime) {
            return max(0, (int) $startTime->diffInSeconds($this->completed_at));
        }

        return null;
    }

    /**
     * Get human-readable allocated Tiger display code (e.g. "Tiger-1", "Special Tiger-2")
     */
    public function getDisplayTigerAttribute(): ?string
    {
        $rawCode = $this->tiger_id;
        if (!$rawCode && $this->inprogress_remarks && preg_match('/\[Allocated (?:Static )?Asset: (.*?)\]/i', $this->inprogress_remarks, $matches)) {
            $rawCode = trim($matches[1]);
        }

        if (!$rawCode) {
            return null;
        }

        if (str_starts_with(strtoupper($rawCode), 'ST')) {
            $num = ltrim(substr($rawCode, 2), '- ');
            return 'Special Tiger-' . $num;
        }

        if (str_starts_with(strtoupper($rawCode), 'T')) {
            $num = ltrim(substr($rawCode, 1), '- ');
            return 'Tiger-' . $num;
        }

        return $rawCode;
    }
}
