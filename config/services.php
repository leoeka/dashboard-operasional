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
        'mockup_candidate_count' => env('OPENAI_MOCKUP_CANDIDATE_COUNT', 3),
        // Dipakai SectionImageService — foto per section (hero + beberapa
        // item) yang disisipkan ke halaman WordPress yang di-generate.
        // Dibatasi section_image_count per project supaya biaya & waktu
        // build tetap terkendali (tiap gambar = 1 panggilan API berbayar).
        'section_image_model' => env('OPENAI_SECTION_IMAGE_MODEL', 'gpt-image-1'),
        'section_image_quality' => env('OPENAI_SECTION_IMAGE_QUALITY', 'low'),
        'section_image_count' => env('OPENAI_SECTION_IMAGE_COUNT', 6),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'builder_model' => env('ANTHROPIC_BUILDER_MODEL', 'claude-sonnet-4-5'),
        // Generating a full theme+plugin (up to 50k output tokens) plus
        // reading several images can take a few minutes; raise via
        // ANTHROPIC_BUILD_TIMEOUT if your host's own PHP execution limit
        // allows more (or needs less).
        'build_timeout' => env('ANTHROPIC_BUILD_TIMEOUT', 480),
        // Only needed if ANTHROPIC_API_KEY is an "identity-linked" key
        // (tied to a personal Console login rather than a workspace-scoped
        // API key) — Anthropic then requires the anthropic-workspace-id
        // header on every request. Find it at console.anthropic.com under
        // Settings > Workspaces (looks like "wrkspc_...").
        'workspace_id' => env('ANTHROPIC_WORKSPACE_ID'),
    ],

    'proposal_ai_enabled' => env('PROPOSAL_AI_ENABLED', true),

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

    'google_places' => [
        'api_key' => env('GOOGLE_PLACES_API_KEY'),
    ],

];
