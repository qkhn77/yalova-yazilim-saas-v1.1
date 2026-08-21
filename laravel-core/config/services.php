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
        'token' => env('POSTMARK_TOKEN'),
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

    'google' => [
        'analytics_id' => env('GOOGLE_ANALYTICS_ID'), // GA4 - yalova_kamera (örn: G-XXXXXXXXXX)
    ],

    'muhasebe_hatirlatma' => [
        'whatsapp' => [
            'provider' => env('MUHASEBE_HATIRLATMA_WHATSAPP_PROVIDER', 'webhook'),
            'webhook_url' => env('MUHASEBE_HATIRLATMA_WHATSAPP_WEBHOOK_URL'),
            'token' => env('MUHASEBE_HATIRLATMA_WHATSAPP_TOKEN'),
        ],
        'sms' => [
            'provider' => env('MUHASEBE_HATIRLATMA_SMS_PROVIDER', 'webhook'),
            'webhook_url' => env('MUHASEBE_HATIRLATMA_SMS_WEBHOOK_URL'),
            'token' => env('MUHASEBE_HATIRLATMA_SMS_TOKEN'),
        ],
    ],

];
