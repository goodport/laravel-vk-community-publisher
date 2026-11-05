<?php

return [

    /*
    |--------------------------------------------------------------------------
    | VK API
    |--------------------------------------------------------------------------
    |
    | The package does not store community credentials. Pass group id and
    | access token per post via CommunityCredentials. These values configure
    | the API endpoint only.
    |
    */
    'api_version' => env('VK_API_VERSION', '5.199'),
    'base_url' => env('VK_API_BASE_URL', 'https://api.vk.ru/method'),
    'timeout' => (int) env('VK_API_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Access tokens are always redacted from logs. Set null to disable logging.
    |
    */
    'log_channel' => env('VK_API_LOG_CHANNEL'),

    /*
    |--------------------------------------------------------------------------
    | Link preview parsing
    |--------------------------------------------------------------------------
    |
    | VK sometimes returns an empty wall.parseAttachedLink response. The
    | publisher retries the request before failing.
    |
    */
    'link_parse_attempts' => (int) env('VK_LINK_PARSE_ATTEMPTS', 3),
    'link_parse_retry_delay_ms' => (int) env('VK_LINK_PARSE_RETRY_DELAY_MS', 500),

    /*
    |--------------------------------------------------------------------------
    | Preview image
    |--------------------------------------------------------------------------
    |
    | VK link cards support up to 1200x630 (retina 1074x480). The factory
    | crops to this ratio and refuses to upscale by default.
    |
    */
    'preview' => [
        'max_width' => 1200,
        'max_height' => 630,
        'min_width' => 537,
        'min_height' => 240,
        'quality' => 92,
    ],

];
