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
        'mockup_model' => env('OPENAI_MOCKUP_MODEL', 'gpt-5-mini'),
        'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
        // `low` is cheap but draws garbled text and soft detail into mockup photos.
        'image_quality' => env('OPENAI_IMAGE_QUALITY', 'medium'),
        'review_generated_images' => env('OPENAI_REVIEW_GENERATED_IMAGES', env('APP_ENV') !== 'testing'),
        'image_review_model' => env('OPENAI_IMAGE_REVIEW_MODEL', 'gpt-4.1-mini'),
        // The account's image rate limit (OpenAI tier 1: 5/min). 0 = no limit.
        'images_per_minute' => env('OPENAI_IMAGES_PER_MINUTE', 5),
        // Seconds one proposal run may spend on photos; the rest are filled on retry.
        'image_time_budget' => env('OPENAI_IMAGE_TIME_BUDGET', 300),
        'mockup_candidate_count' => env('OPENAI_MOCKUP_CANDIDATE_COUNT', 3),
        'wordpress_builder_model' => env('OPENAI_WORDPRESS_BUILDER_MODEL', 'gpt-5.6'),
        'wordpress_build_timeout' => env('OPENAI_WORDPRESS_BUILD_TIMEOUT', 600),
        'wordpress_max_output_tokens' => env('OPENAI_WORDPRESS_MAX_OUTPUT_TOKENS', 50000),
    ],


    'proposal_ai_enabled' => env('PROPOSAL_AI_ENABLED', true),

    'fonnte' => [
        'token' => env('FONNTE_TOKEN'),
    ],

    // Shared Google OAuth client (key name kept as-is to avoid touching
    // every call site). Used by SearchConsoleService, GoogleAnalyticsService
    // and the google:get-refresh-token command. The Ads-only fields
    // (refresh_token / developer_token / customer_id / login_customer_id)
    // were removed together with GoogleAdsKeywordService — the team gets
    // keyword volume from Search Console, not the Google Ads API.
    'google_ads' => [
        'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
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

    'google_places' => [
        'api_key' => env('GOOGLE_PLACES_API_KEY'),
    ],

];
