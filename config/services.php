<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'okta' => [
        'client_id' => env('OKTA_CLIENT_ID'),
        'client_secret' => env('OKTA_CLIENT_SECRET'),
        'redirect' => env('OKTA_REDIRECT_URI', '/auth/okta/callback'),

        // Your org URL, e.g. https://dev-12345678.okta.com
        'base_url' => env('OKTA_BASE_URL'),

        // 'default' selects Okta's default custom authorization server rather
        // than the org server. The custom one can be taught to emit a groups
        // claim; the org server cannot, and group mapping needs it.
        'auth_server_id' => env('OKTA_AUTH_SERVER_ID', 'default'),

        // Authentication and provisioning are separate questions. Okta can
        // prove who someone is without that implying they should have an
        // account here. Left off, a stranger who authenticates successfully is
        // still refused, and SCIM remains the only thing that creates users.
        // Turn it on only where the directory is the sole source of truth and
        // every assigned user is meant to have access.
        'jit_provisioning' => env('OKTA_JIT_PROVISIONING', false),
    ],

];
