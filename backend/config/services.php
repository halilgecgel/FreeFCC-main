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

    'evolution' => [
        'url' => env('EVOLUTION_API_URL', 'http://127.0.0.1:8080'),
        'key' => env('EVOLUTION_API_KEY'),
        'instance' => env('EVOLUTION_API_INSTANCE', 'freefcc'),
        // Flight notices: group ID is resolved live from the Evolution instance by name
        'flight_group_name' => env('EVOLUTION_FLIGHT_GROUP_NAME', 'ŞANLIURFA DRONE PİLOTLARI'),
        // Temporary override: when set, flight notices go to this number instead of the group
        'flight_notify_to' => env('EVOLUTION_FLIGHT_NOTIFY_TO'),
        // Skip start notice only if previous enable/auto_fcc is within this many minutes (no disable in between)
        'flight_reapply_cooldown_minutes' => (int) env('EVOLUTION_FLIGHT_REAPPLY_COOLDOWN_MINUTES', 15),
    ],

    'sms' => [
        'provider' => env('SMS_PROVIDER', 'log'),
        'netgsm' => [
            'usercode' => env('NETGSM_USERCODE'),
            'password' => env('NETGSM_PASSWORD'),
            'header' => env('NETGSM_HEADER', 'FREEFCC'),
        ],
        'vatansms' => [
            'api_id' => env('VATANSMS_API_ID'),
            'api_key' => env('VATANSMS_API_KEY'),
            'sender' => env('VATANSMS_SENDER', 'FREEFCC'),
        ],
    ],

];
