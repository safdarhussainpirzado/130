<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginLog extends Model
{
    public $timestamps = false;
    const CREATED_AT = 'login_at';

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'login_at',
        'logout_at',
        'session_hash',
        'request_id',
        'node',
        'country',
        'asn',
        'org',
        'is_datacenter',
        'is_vpn_or_proxy',
        'is_tor',
        'geo_decision',
        'risk_score',
        'risk_reasons',
        'device_hash',
    ];

    protected $casts = [
        'is_datacenter'   => 'boolean',
        'is_vpn_or_proxy' => 'boolean',
        'is_tor'          => 'boolean',
        'risk_reasons'    => 'array',
        'risk_score'      => 'integer',
        'login_at'        => 'datetime',
        'logout_at'       => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (LoginLog $l) => $l->forceFill(\App\Support\ForensicContext::all()));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
