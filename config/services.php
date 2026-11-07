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
        // Website author: sitemap + real copy for the proposal. Streaming is
        // what keeps a long JSON answer alive, so the timeout is generous.
        'website_content_model' => env('OPENAI_WEBSITE_CONTENT_MODEL', 'gpt-5.6'),
        'website_content_timeout' => env('OPENAI_WEBSITE_CONTENT_TIMEOUT', 300),
        // One call's ceiling, and gpt-5.6 bills by it: 32.768 output tokens is
        // ~$0,98 for a single content call at the official $30/M. No proposal
        // can afford that happening on every stage retry, so the ceiling is
        // cut in half. 8.192 would be cheaper still, but nothing has measured
        // this stage's real answer length yet - the usage recorder will.
        'website_content_max_output_tokens' => env('OPENAI_WEBSITE_CONTENT_MAX_OUTPUT_TOKENS', 16384),
    ],
    'fonnte' => [
        'token' => env('FONNTE_TOKEN'),
        'delay' => env('FONNTE_DELAY', '60-180'),
    ],

    // Shared Google OAuth client (key name kept as-is to avoid touching
    // Shared OAuth client plus Google Ads API credentials.
    'google_ads' => [
        'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_ADS_REFRESH_TOKEN'),
        'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
        'customer_id' => env('GOOGLE_ADS_CUSTOMER_ID'),
        'login_customer_id' => env('GOOGLE_ADS_LOGIN_CUSTOMER_ID'),
        'api_version' => env('GOOGLE_ADS_API_VERSION', 'v26'),
        'geo_target_constants' => array_filter(array_map('trim', explode(',', env('GOOGLE_ADS_GEO_TARGET_CONSTANTS', 'geoTargetConstants/2376')))),
        'language_constant' => env('GOOGLE_ADS_LANGUAGE_CONSTANT', 'languageConstants/1022'),
    ],

    'google_custom_search' => [
        'api_key' => env('GOOGLE_CUSTOM_SEARCH_API_KEY'),
        'engine_id' => env('GOOGLE_CUSTOM_SEARCH_ENGINE_ID'),
    ],

    'mockup' => [
        'openai' => [
            'model' => env('OPENAI_MOCKUP_MODEL', 'gpt-6-astra'),
            'image_model' => env('OPENAI_MOCKUP_IMAGE_MODEL', 'gpt-image-2.5-sunburst'),
            'timeout' => (int) env('OPENAI_MOCKUP_TIMEOUT', 2100),
            'max_output_tokens' => (int) env('OPENAI_MOCKUP_MAX_OUTPUT_TOKENS', 50000),
            'max_input_image_bytes' => (int) env('OPENAI_MOCKUP_MAX_INPUT_IMAGE_BYTES', 12000000),
            'max_generated_image_bytes' => (int) env('OPENAI_MOCKUP_MAX_GENERATED_IMAGE_BYTES', 20000000),
        ],
        'base' => env('MOCKUP_BASE'),
        'work' => env('MOCKUP_WORK'),
        'codex' => [
            // Jalur penuh, jangan bergantung pada PATH milik proses web.
            'node' => env('MOCKUP_CODEX_NODE', 'node'),
            'script' => env('MOCKUP_CODEX_SCRIPT'),
            'model' => env('MOCKUP_CODEX_MODEL'),
            // Batas keras satu kandidat. Lewat ini proses dimatikan bersama anaknya.
            // Satu kandidat sekarang membuat gambar rancangan lalu 5 foto sendiri,
            // jadi 20 menit pernah kehabisan waktu di tengah jalan.
            'timeout' => env('MOCKUP_CODEX_TIMEOUT', 2100),
            // Folder login Codex (bawaan ~/.codex). Hanya dibaca untuk gambar.
            'home' => env('MOCKUP_CODEX_HOME'),
            // Tempat meja kerja khusus uji; kosong = storage/app/mockup-work.
            'base' => env('MOCKUP_CODEX_BASE'),
            // Folder kerja sementara. Kosong = folder temp sistem, di luar repo,
            // supaya Codex tidak membaca AGENTS.md/DESIGN.md milik aplikasi ini.
            'work' => env('MOCKUP_CODEX_WORK'),
        ],
    ],

    'browsershot' => [
        'node_binary' => env('BROWSERSHOT_NODE_BINARY', 'node'),
        'node_module_path' => env('BROWSERSHOT_NODE_MODULE_PATH', base_path('node_modules')),
        'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
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
