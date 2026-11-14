<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Geo Access Control Mode
    |--------------------------------------------------------------------------
    | 'off'     - completely disabled; no lookups or audits.
    | 'shadow'  - (default) evaluate and audit all events (geo_login_shadow_would_deny),
    |             never deny or disrupt operator logins.
    | 'enforce' - strictly enforce geo/risk policies; deny unauthorized logins.
    */
    'mode' => env('GEO_ACCESS_MODE', 'shadow'),

    /*
    |--------------------------------------------------------------------------
    | Static Emergency Override Switch
    |--------------------------------------------------------------------------
    | When true, immediately bypasses all enforcement and allows all logins.
    | Can also be toggled at runtime via `php artisan geo:override`.
    */
    'emergency_override' => (bool) env('GEO_EMERGENCY_OVERRIDE', false),

    /*
    |--------------------------------------------------------------------------
    | Allowed Countries
    |--------------------------------------------------------------------------
    | Standard geographic whitelist. Defaults to Pakistan only.
    */
    'allowed_countries' => [
        'PK',
    ],

    /*
    |--------------------------------------------------------------------------
    | Global CIDR Allow-List
    |--------------------------------------------------------------------------
    | Trusted corporate, government, NTC and local gateway networks.
    | Any IP matching this list is unconditionally allowed regardless of country/ASN.
    */
    'cidr_allowlist' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'GEO_CIDR_ALLOWLIST',
        '127.0.0.1/32,::1/128,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'
    ))))),

    /*
    |--------------------------------------------------------------------------
    | Fixed CIDR Allow-List for Admin Roles
    |--------------------------------------------------------------------------
    | When populated, privileged roles (super_admin, operation_admin) can ONLY
    | log in from these specific IP subnets (e.g. CPO HQ / NTC Gateway).
    | When empty, admins can log in from any allowed Pakistan / global CIDR.
    */
    'admin_cidr_allowlist' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'GEO_ADMIN_CIDR_ALLOWLIST',
        ''
    ))))),

    /*
    |--------------------------------------------------------------------------
    | Per-Role Strictness & Security Policies
    |--------------------------------------------------------------------------
    */
    'roles' => [
        'super_admin' => [
            'strict_pakistan_only'  => true,
            'block_tor'             => true,
            'block_vpn_proxy'       => true,
            'block_datacenter'      => true,
            'max_idle_minutes'      => 15,
            'single_active_session' => true,
        ],
        'operation_admin' => [
            'strict_pakistan_only'  => true,
            'block_tor'             => true,
            'block_vpn_proxy'       => true,
            'block_datacenter'      => true,
            'max_idle_minutes'      => 15,
            'single_active_session' => true,
        ],
        'default' => [
            'strict_pakistan_only'  => true,
            'block_tor'             => false, // Alert only for operators; do not block helpline
            'block_vpn_proxy'       => false, // Tolerant to mobile carrier NATs and proxies
            'block_datacenter'      => false,
            'max_idle_minutes'      => 120,
            'single_active_session' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Named User Exceptions
    |--------------------------------------------------------------------------
    | Time-bound, audited user exemptions (e.g. officers on foreign deployment/training).
    | Format: username => [
    |     'expires_at' => '2026-12-31 23:59:59',
    |     'owner' => 'super_admin',
    |     'allowed_countries' => ['*'] or ['AE', 'GB'],
    |     'reason' => 'Official Duty / UN Mission'
    | ]
    */
    'user_exceptions' => [
        // 'cpo_liaison' => [
        //     'expires_at' => '2026-12-31 23:59:59',
        //     'owner' => 'super_admin',
        //     'allowed_countries' => ['*'],
        //     'reason' => 'Official UN Mission',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Advanced Anomaly Detections (Shadow First)
    |--------------------------------------------------------------------------
    */
    'alert_new_device'  => (bool) env('GEO_ALERT_NEW_DEVICE', true),
    'alert_new_country' => (bool) env('GEO_ALERT_NEW_COUNTRY', true),
    'impossible_travel' => [
        'enabled'                     => (bool) env('GEO_IMPOSSIBLE_TRAVEL', true),
        'min_hours_between_countries' => (float) env('GEO_IMPOSSIBLE_TRAVEL_HOURS', 3.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Separate Login Rate Limits (NAT Friendly)
    |--------------------------------------------------------------------------
    | Decouples per-account brute force mitigation from shared carrier IP pools.
    */
    'rate_limits' => [
        'per_username_per_minute' => (int) env('GEO_RATE_LIMIT_USER', 5),
        'per_ip_per_minute'       => (int) env('GEO_RATE_LIMIT_IP', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stricter Admin Session Governance
    |--------------------------------------------------------------------------
    */
    'strict_admin_sessions' => (bool) env('GEO_STRICT_ADMIN_SESSIONS', true),

    /*
    |--------------------------------------------------------------------------
    | File Storage Path
    |--------------------------------------------------------------------------
    */
    'storage_path' => storage_path('app/geo'),
];
