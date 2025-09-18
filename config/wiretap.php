<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Wiretap Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch to enable or disable all Wiretap functionality.
    | When disabled, all logging and webhook calls will be ignored.
    |
    */

    'enabled' => env('WIRETAP_ENABLED', true),

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
        | Webhook Secret
        |--------------------------------------------------------------------------
        |
        | Bearer token secret for webhook authentication. When configured,
        | the Authorization: Bearer {secret} header will be included with
        | all webhook requests.
        |
        */

        'secret' => env('WIRETAP_WEBHOOK_SECRET'),

        /*
        |--------------------------------------------------------------------------
        | Custom Headers
        |--------------------------------------------------------------------------
        |
        | Additional headers to send with webhook requests. Useful for
        | authentication tokens, API keys, etc. Note: If webhook secret is
        | configured above, Authorization header will be automatically added.
        |
        */

        'headers' => [
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

    /*
    |--------------------------------------------------------------------------
    | Exception Level Mapping
    |--------------------------------------------------------------------------
    |
    | Configure how different exception types should be logged. This allows
    | for smart filtering to reduce noise from expected exceptions like
    | validation errors, 404s, and authentication failures.
    |
    | Supported levels: 'emergency', 'alert', 'critical', 'error', 'warning',
    | 'notice', 'info', 'debug', 'skip'
    |
    | Use 'skip' to completely ignore certain exception types.
    |
    */

    'exception_levels' => [

        /*
        |--------------------------------------------------------------------------
        | Laravel Framework Exceptions
        |--------------------------------------------------------------------------
        */

        // Validation errors (form validation failures)
        'Illuminate\Validation\ValidationException' => 'info',

        // Authentication required
        'Illuminate\Auth\AuthenticationException' => 'warning',

        // Authorization failures
        'Illuminate\Auth\Access\AuthorizationException' => 'warning',

        // Model not found (404s)
        'Illuminate\Database\Eloquent\ModelNotFoundException' => 'info',

        /*
        |--------------------------------------------------------------------------
        | Symfony HTTP Exceptions
        |--------------------------------------------------------------------------
        */

        // 404 Not Found
        'Symfony\Component\HttpKernel\Exception\NotFoundHttpException' => 'info',

        // 403 Forbidden
        'Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException' => 'warning',

        // 405 Method Not Allowed
        'Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException' => 'info',

        // 422 Unprocessable Entity
        'Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException' => 'info',

        // 429 Too Many Requests
        'Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException' => 'warning',

        // 400 Bad Request
        'Symfony\Component\HttpKernel\Exception\BadRequestHttpException' => 'warning',

        /*
        |--------------------------------------------------------------------------
        | General HTTP Exceptions
        |--------------------------------------------------------------------------
        |
        | For general HTTP exceptions, you can use a closure to determine
        | the log level based on the HTTP status code.
        |
        */

        'Symfony\Component\HttpKernel\Exception\HttpException' => function ($exception) {
            $statusCode = method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500;

            if ($statusCode >= 500) {
                return 'error';  // Server errors are actual errors
            } elseif ($statusCode >= 400) {
                return 'info';   // Client errors are usually expected
            }

            return 'warning';
        },

        /*
        |--------------------------------------------------------------------------
        | Default Level
        |--------------------------------------------------------------------------
        |
        | The default log level for exceptions that don't match any of the
        | specific mappings above.
        |
        */

        'default' => 'error',

    ],

];
