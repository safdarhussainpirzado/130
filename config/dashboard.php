<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dashboard Architecture Feature Flag
    |--------------------------------------------------------------------------
    |
    | When enabled, the dashboard utilizes the Unified Hourly Rollup Cube,
    | Redis SWR caching, and decoupled progressive API delivery (/api/dashboard/*)
    | eliminating full-table scans and massive inline JSON blobs.
    |
    */
    'use_v2_analytics' => env('DASHBOARD_USE_V2_ANALYTICS', true),

    /*
    |--------------------------------------------------------------------------
    | SWR Cache Policies
    |--------------------------------------------------------------------------
    */
    'swr' => [
        'live_ttl'        => env('DASHBOARD_LIVE_TTL', 5),        // 5 seconds
        'live_grace'      => env('DASHBOARD_LIVE_GRACE', 15),     // 15 seconds grace
        'analytics_ttl'   => env('DASHBOARD_ANALYTICS_TTL', 60),  // 60 seconds
        'analytics_grace' => env('DASHBOARD_ANALYTICS_GRACE', 300), // 5 minutes grace
        'reporting_ttl'   => env('DASHBOARD_REPORTING_TTL', 300), // 5 minutes
        'reporting_grace' => env('DASHBOARD_REPORTING_GRACE', 900), // 15 minutes grace
    ],
];
