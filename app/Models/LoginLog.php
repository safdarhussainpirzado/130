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
        'city',
        'region',
        'latitude',
        'longitude',
        'is_foreign',
        'browser',
        'browser_version',
        'os',
        'os_version',
        'device_type',
    ];

    protected $casts = [
        'is_datacenter'   => 'boolean',
        'is_vpn_or_proxy' => 'boolean',
        'is_tor'          => 'boolean',
        'is_foreign'      => 'boolean',
        'latitude'        => 'float',
        'longitude'       => 'float',
        'risk_reasons'    => 'array',
        'risk_score'      => 'integer',
        'login_at'        => 'datetime',
        'logout_at'       => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (LoginLog $l) => $l->forceFill(\App\Support\ForensicContext::all()));
    }

    /** Geo / client fields from a GeoPolicyEngine::evaluate() result, ready for LoginLog::create(). */
    public static function geoAttributes(array $e): array
    {
        return [
            'country'         => $e['country'],
            'asn'             => $e['asn'],
            'org'             => $e['org'],
            'is_datacenter'   => $e['is_datacenter'],
            'is_vpn_or_proxy' => $e['is_vpn_or_proxy'],
            'is_tor'          => $e['is_tor'],
            'geo_decision'    => $e['decision'],
            'risk_score'      => $e['risk_score'],
            'risk_reasons'    => $e['risk_reasons'],
            'device_hash'     => $e['device_hash'],
            'city'            => $e['city'] ?? null,
            'region'          => $e['region'] ?? null,
            'latitude'        => $e['latitude'] ?? null,
            'longitude'       => $e['longitude'] ?? null,
            'is_foreign'      => $e['is_foreign'] ?? null,
            'browser'         => $e['browser'] ?? null,
            'browser_version' => $e['browser_version'] ?? null,
            'os'              => $e['os'] ?? null,
            'os_version'      => $e['os_version'] ?? null,
            'device_type'     => $e['device_type'] ?? null,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
