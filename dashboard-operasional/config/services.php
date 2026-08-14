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

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

    'zipwp' => [
        'token' => env('ZIPWP_API_TOKEN'),
        'mcp_url' => env('ZIPWP_MCP_URL', 'https://api.zipwp.com/mcp/zipwp'),
    ],

    'fonnte' => [
        'token' => env('FONNTE_TOKEN'),
    ],

    'google_ads' => [
        'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_ADS_REFRESH_TOKEN'),
        'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
        'customer_id' => env('GOOGLE_ADS_CUSTOMER_ID'),
        'login_customer_id' => env('GOOGLE_ADS_LOGIN_CUSTOMER_ID'),
    ],

    'google_custom_search' => [
        'api_key' => env('GOOGLE_CUSTOM_SEARCH_API_KEY'),
        'engine_id' => env('GOOGLE_CUSTOM_SEARCH_ENGINE_ID'),
    ],

    'pagespeed' => [
        'api_key' => env('PAGESPEED_API_KEY'),
    ],

    'google_search_console' => [
        'refresh_token' => env('GOOGLE_SEARCH_CONSOLE_REFRESH_TOKEN'),
    ],

    'google_analytics' => [
        'refresh_token' => env('GOOGLE_ANALYTICS_REFRESH_TOKEN'),
    ],

];
