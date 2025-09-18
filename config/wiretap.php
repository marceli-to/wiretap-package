<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel Logging Integration
    |--------------------------------------------------------------------------
    |
    | Whether to also log messages to Laravel's default logging system.
    |
    */

    'log_to_laravel' => env('WIRETAP_LARAVEL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configure webhook settings for sending logs to external applications.
    |
    */

    'webhook' => [

        /*
        |--------------------------------------------------------------------------
        | Webhook Enabled
        |--------------------------------------------------------------------------
        |
        | Enable or disable webhook functionality.
        |
        */

        'enabled' => env('WIRETAP_WEBHOOK_ENABLED', false),

        /*
        |--------------------------------------------------------------------------
        | Webhook URL
        |--------------------------------------------------------------------------
        |
        | The URL endpoint where log data will be sent via POST request.
        |
        */

        'url' => env('WIRETAP_WEBHOOK_URL'),

        /*
        |--------------------------------------------------------------------------
        | Custom Headers
        |--------------------------------------------------------------------------
        |
        | Additional headers to send with webhook requests. Useful for
        | authentication tokens, API keys, etc.
        |
        */

        'headers' => [
            // 'Authorization' => 'Bearer your-token-here',
            // 'X-API-Key' => 'your-api-key',
        ],

        /*
        |--------------------------------------------------------------------------
        | Log Webhook Failures
        |--------------------------------------------------------------------------
        |
        | Whether to log webhook failures to Laravel's default logger.
        |
        */

        'log_failures' => env('WIRETAP_WEBHOOK_LOG_FAILURES', true),

    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout in seconds for webhook HTTP requests.
    |
    */

    'timeout' => env('WIRETAP_TIMEOUT', 5),

];
