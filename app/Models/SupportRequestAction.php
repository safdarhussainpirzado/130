<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportRequestAction extends Model
{
    use HasFactory;

    public $timestamps = false; // Uses created_at only (immutable audit record)

    protected $fillable = [
        'support_request_id',
        'actor_id',
        'action_type',
        'previous_status',
        'new_status',
        'authentication_method',
        'remarks',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportRequestAction $action): void {
            if (empty($action->created_at)) {
                $action->created_at = \Illuminate\Support\Carbon::now();
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Action Type Constants                                             */
    /* ------------------------------------------------------------------ */

    public const ACTION_SUBMITTED                 = 'submitted';
    public const ACTION_SOURCE_APPROVED           = 'source_approved';
    public const ACTION_SOURCE_REJECTED           = 'source_rejected';
    public const ACTION_SOURCE_ZONE_APPROVED      = 'source_zone_approved';
    public const ACTION_NATIONAL_APPROVED         = 'national_approved';
    public const ACTION_DEST_ZONE_APPROVED        = 'dest_zone_approved';
    public const ACTION_DEST_SECTOR_APPROVED      = 'dest_sector_approved';
    public const ACTION_DESTINATION_CONFIRMED     = 'destination_confirmed';
    public const ACTION_DESTINATION_REJECTED      = 'destination_rejected';
    public const ACTION_TRANSFER_REJECTED         = 'transfer_rejected';
    public const ACTION_MANAGER_APPROVED          = 'manager_approved';
    public const ACTION_MANAGER_REJECTED          = 'manager_rejected';
    public const ACTION_CANCELLED                 = 'cancelled';
    public const ACTION_ACCOUNT_LOCKED            = 'account_locked';
    public const ACTION_ACCOUNT_UNLOCKED          = 'account_unlocked';
    public const ACTION_PASSWORD_RESET_ISSUED     = 'password_reset_issued';
    public const ACTION_PASSWORD_RESET_COMPLETED  = 'password_reset_completed';
    public const ACTION_MFA_TOKEN_ISSUED          = 'mfa_token_issued';

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                     */
    /* ------------------------------------------------------------------ */

    public function supportRequest(): BelongsTo
    {
        return $this->belongsTo(SupportRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /* ------------------------------------------------------------------ */
    /*  Presentation Helpers                                              */
    /* ------------------------------------------------------------------ */

    public function getActionLabel(): string
    {
        return match ($this->action_type) {
            self::ACTION_SUBMITTED                => 'Request Submitted',
            self::ACTION_SOURCE_APPROVED          => 'Source Sector Departure Approved',
            self::ACTION_SOURCE_REJECTED          => 'Source Departure Rejected',
            self::ACTION_SOURCE_ZONE_APPROVED     => 'Source Zone Endorsed',
            self::ACTION_NATIONAL_APPROVED        => 'National Operations Review Approved',
            self::ACTION_DEST_ZONE_APPROVED       => 'Destination Zone Clearance Approved',
            self::ACTION_DEST_SECTOR_APPROVED     => 'Destination Sector Clearance Approved',
            self::ACTION_DESTINATION_CONFIRMED    => 'Destination Arrival Confirmed',
            self::ACTION_DESTINATION_REJECTED,
            self::ACTION_TRANSFER_REJECTED        => 'Transfer Request Rejected',
            self::ACTION_MANAGER_APPROVED         => 'Operation Manager Approved',
            self::ACTION_MANAGER_REJECTED         => 'Operation Manager Rejected',
            self::ACTION_CANCELLED                => 'Request Cancelled by Applicant',
            self::ACTION_ACCOUNT_LOCKED           => 'Immediate Account Lock Initiated',
            self::ACTION_ACCOUNT_UNLOCKED         => 'Account Unlocked by Operation Manager',
            self::ACTION_PASSWORD_RESET_ISSUED    => 'Password Reset Token Issued',
            self::ACTION_PASSWORD_RESET_COMPLETED => 'Password Successfully Reset',
            self::ACTION_MFA_TOKEN_ISSUED         => 'MFA Enrollment Token Authorized',
            default                               => ucwords(str_replace('_', ' ', $this->action_type)),
        };
    }

    public function getActionBadgeClass(): string
    {
        return match ($this->action_type) {
            self::ACTION_SUBMITTED                => 'bg-blue-50 text-blue-700 border-blue-200',
            self::ACTION_SOURCE_APPROVED,
            self::ACTION_SOURCE_ZONE_APPROVED,
            self::ACTION_NATIONAL_APPROVED,
            self::ACTION_DEST_ZONE_APPROVED,
            self::ACTION_DEST_SECTOR_APPROVED,
            self::ACTION_DESTINATION_CONFIRMED,
            self::ACTION_MANAGER_APPROVED,
            self::ACTION_ACCOUNT_UNLOCKED,
            self::ACTION_PASSWORD_RESET_COMPLETED => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::ACTION_SOURCE_REJECTED,
            self::ACTION_DESTINATION_REJECTED,
            self::ACTION_TRANSFER_REJECTED,
            self::ACTION_MANAGER_REJECTED,
            self::ACTION_ACCOUNT_LOCKED           => 'bg-rose-50 text-rose-700 border-rose-200',
            self::ACTION_CANCELLED                => 'bg-slate-100 text-slate-700 border-slate-200',
            default                               => 'bg-indigo-50 text-indigo-700 border-indigo-200',
        };
    }

    public function getAuthMethodLabel(): string
    {
        return match ($this->authentication_method) {
            'password_totp' => 'Password + Authenticator TOTP',
            'password'      => 'Password Verification',
            'webauthn'      => 'Hardware Security Key (FIDO2 / WebAuthn)',
            'session'       => 'Authenticated Active Session',
            default         => ucfirst($this->authentication_method),
        };
    }
}
