<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAssignmentHistory extends Model
{
    use HasFactory;

    protected $table = 'user_assignment_history';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'previous_office_id',
        'new_office_id',
        'transfer_request_id',
        'changed_by',
        'reason',
        'effective_at',
        'created_at',
    ];

    protected $casts = [
        'effective_at' => 'datetime',
        'created_at'   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (UserAssignmentHistory $history): void {
            if (empty($history->effective_at)) {
                $history->effective_at = \Illuminate\Support\Carbon::now();
            }
            if (empty($history->created_at)) {
                $history->created_at = \Illuminate\Support\Carbon::now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function previousOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'previous_office_id');
    }

    public function newOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'new_office_id');
    }

    public function transferRequest(): BelongsTo
    {
        return $this->belongsTo(SupportRequest::class, 'transfer_request_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
