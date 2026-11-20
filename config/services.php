<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'freeroute' => [
        'api_key'  => env('FREEROUTE_API_KEY'),
        'base_url' => env('FREEROUTE_BASE_URL', 'https://api.maps.freeroute.org'),
        'timeout'  => 30,
    ],

    'push' => [
        'enabled' => (bool) env('PUSH_ENABLED', false),
        'queue'   => env('PUSH_QUEUE', 'push'),
    ],

    'webpush' => [
        'enabled'          => (bool) env('PUSH_ENABLED', false),
        'public_key'       => env('VAPID_PUBLIC_KEY'),
        'private_key'      => env('VAPID_PRIVATE_KEY'),
        'private_key_file' => env('VAPID_PRIVATE_KEY_FILE', base_path('secrets/vapid_private_key')),
        'subject'          => env('VAPID_SUBJECT', 'mailto:admin@130.nhmp.gov.pk'),
    ],

    'firebase' => [
        'enabled'          => (bool) env('PUSH_FCM_ENABLED', false),
        'project_id'       => env('FIREBASE_PROJECT_ID'),
        'credentials_file' => env('FIREBASE_CREDENTIALS_FILE', base_path('secrets/firebase_credentials.json')),
    ],

];
