# Wiretap Package - Laravel Logging with Webhook Support

A Laravel package for centralized logging with webhook capabilities to send structured logs to external dashboards.

## Installation

### 1. Install via Composer

```bash
composer require marceli-to/wiretap
```

### 2. Publish Configuration (Optional)

```bash
php artisan vendor:publish --provider="MarceliTo\Wiretap\Providers\WiretapServiceProvider"
```

This will create `config/wiretap.php` with configuration options.

## Configuration

### Environment Variables

Add these variables to your `.env` file:

```env
# Master switch to enable/disable all Wiretap functionality
WIRETAP_ENABLED=true

# Enable webhook functionality
WIRETAP_WEBHOOK_ENABLED=true

# Your Wiretap dashboard webhook URL
WIRETAP_WEBHOOK_URL=https://your-wiretap-dashboard.com/api/webhook/logs

# Webhook authentication secret (highly recommended)
WIRETAP_WEBHOOK_SECRET=your-secret-key-here

# Application name (will appear in dashboard)
WIRETAP_APP_NAME="Your Application Name"

# Optional: Also log to Laravel's default log files
WIRETAP_LARAVEL_ENABLED=true

# Optional: Log webhook failures to Laravel logs
WIRETAP_WEBHOOK_LOG_FAILURES=true

# Optional: HTTP timeout for webhook requests (seconds)
WIRETAP_TIMEOUT=10
```

### Webhook Authentication

For webhook authentication, the package supports Bearer token authentication via the `WIRETAP_WEBHOOK_SECRET` environment variable (recommended approach):

```env
WIRETAP_WEBHOOK_SECRET=your-secret-key-here
```

This automatically adds the `Authorization: Bearer your-secret-key-here` header to all webhook requests.

### Custom Headers (Advanced)

For additional headers beyond authentication, edit `config/wiretap.php`:

```php
'webhook' => [
    'headers' => [
        'X-API-Key' => env('WIRETAP_API_KEY'),
        'X-Custom-Header' => 'custom-value',
    ],
],
```

Then add to `.env`:
```env
WIRETAP_API_KEY=your-additional-api-key
```

**Note:** If you configure both `WIRETAP_WEBHOOK_SECRET` and custom `Authorization` header, the webhook secret takes precedence.

## Usage

### Basic Logging

```php
use MarceliTo\Wiretap\Facades\Wiretap;

// Log levels
Wiretap::info('User logged in', ['user_id' => 123]);
Wiretap::error('Database connection failed', ['error' => $exception->getMessage()]);
Wiretap::warning('Low disk space detected', ['available' => '2GB']);
Wiretap::debug('Cache miss', ['key' => 'user:123']);

// Custom log level
Wiretap::log('custom', 'Custom event occurred', ['data' => $someData]);
```

### In Controllers

```php
class UserController extends Controller
{
    public function store(Request $request)
    {
        try {
            $user = User::create($request->validated());

            Wiretap::info('New user registered', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json($user, 201);
        } catch (\Exception $e) {
            Wiretap::error('User registration failed', [
                'error' => $e->getMessage(),
                'input' => $request->except(['password']),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
}
```

### Exception Handling

Wiretap includes smart exception filtering to reduce noise from expected exceptions like validation errors, 404s, and authentication failures.

#### Smart Exception Logging (Recommended)

Add to `app/Exceptions/Handler.php`:

```php
<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use MarceliTo\Wiretap\Facades\Wiretap;
use Throwable;

class Handler extends ExceptionHandler
{
    // ... existing code ...

    public function report(Throwable $exception)
    {
        // Smart exception filtering - automatically categorizes by severity
        Wiretap::exception($exception, [
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'user_id' => auth()->id(),
            'ip' => request()->ip()
        ]);

        parent::report($exception);
    }
}
```

This automatically logs:
- **ValidationException** as `info` (form validation failures)
- **AuthenticationException** as `warning` (login required)
- **ModelNotFoundException** as `info` (404 errors)
- **HTTP 4xx exceptions** as `info` (client errors)
- **HTTP 5xx exceptions** as `error` (server errors)
- **Other exceptions** as `error` (actual problems)

#### Manual Exception Logging

For explicit control over logging levels:

```php
public function report(Throwable $exception)
{
    Wiretap::error('Exception occurred', [
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'url' => request()->fullUrl(),
        'method' => request()->method(),
        'user_id' => auth()->id(),
        'ip' => request()->ip()
    ]);

    parent::report($exception);
}
```

#### Conditional Logging

Use `errorIf()` for conditional error logging:

```php
// Only log as error if it's a critical condition
Wiretap::errorIf($user->isAdmin(), 'Admin action failed', [
    'action' => $action,
    'user_id' => $user->id
]);

// Traditional approach (more verbose)
if ($user->isAdmin()) {
    Wiretap::error('Admin action failed', [
        'action' => $action,
        'user_id' => $user->id
    ]);
}
```

### Job/Queue Logging

```php
class ProcessOrderJob implements ShouldQueue
{
    public function handle()
    {
        try {
            Wiretap::info('Processing order job started', [
                'order_id' => $this->order->id,
                'queue' => $this->queue
            ]);

            // Process order...

            Wiretap::info('Order processed successfully', [
                'order_id' => $this->order->id,
                'processing_time' => $processingTime
            ]);
        } catch (\Exception $e) {
            Wiretap::error('Order processing failed', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
```

### Custom Events

```php
// User activity tracking
Wiretap::info('User action', [
    'action' => 'profile_updated',
    'user_id' => auth()->id(),
    'changes' => $user->getChanges(),
    'timestamp' => now()->toISOString()
]);

// Performance monitoring
Wiretap::info('Database query performance', [
    'query' => 'SELECT * FROM users WHERE active = 1',
    'execution_time' => $executionTime,
    'rows_affected' => $rowCount
]);

// Security events
Wiretap::warning('Suspicious login attempt', [
    'ip' => $request->ip(),
    'user_agent' => $request->userAgent(),
    'failed_attempts' => $failedAttempts,
    'location' => $geoLocation
]);
```

## Alternative Usage (Without Facade)

```php
// Direct class usage
use MarceliTo\Wiretap\Wiretap;

$wiretap = new Wiretap();
$wiretap->info('Direct usage example', ['test' => true]);

// Or using Laravel's service container
app('wiretap')->info('Service container usage', ['test' => true]);
```

## Testing Your Setup

### Quick Test

```bash
php artisan tinker
```

Then run:
```php
\Wiretap::info('Testing Wiretap integration', [
    'test' => true,
    'timestamp' => now()->toISOString()
]);
```

### Webhook Payload Format

The package sends data in this format:

```json
{
    "timestamp": "2024-01-15T10:30:00.000Z",
    "level": "info",
    "message": "User logged in",
    "context": {
        "user_id": 123,
        "ip": "192.168.1.1"
    },
    "app": {
        "name": "Your Application Name",
        "env": "production",
        "url": "https://yourapp.com"
    },
    "server": {
        "hostname": "web-server-01",
        "ip": "10.0.0.1"
    }
}
```

## Configuration Options

The `config/wiretap.php` file contains:

```php
return [
    // Master switch to enable/disable all Wiretap functionality
    'enabled' => env('WIRETAP_ENABLED', true),

    // Enable Laravel logging integration
    'log_to_laravel' => env('WIRETAP_LARAVEL_ENABLED', true),

    'webhook' => [
        // Enable/disable webhook functionality
        'enabled' => env('WIRETAP_WEBHOOK_ENABLED', false),

        // Webhook endpoint URL
        'url' => env('WIRETAP_WEBHOOK_URL'),

        // Bearer token secret for webhook authentication
        'secret' => env('WIRETAP_WEBHOOK_SECRET'),

        // Additional custom headers (Authorization header is automatically
        // added if webhook secret is configured above)
        'headers' => [
            // 'X-API-Key' => 'additional-key',
        ],

        // Log webhook failures
        'log_failures' => env('WIRETAP_WEBHOOK_LOG_FAILURES', true),
    ],

    // HTTP timeout for webhook requests
    'timeout' => env('WIRETAP_TIMEOUT', 5),

    // Exception level mapping for smart filtering
    'exception_levels' => [
        // Laravel Framework Exceptions
        'Illuminate\Validation\ValidationException' => 'info',
        'Illuminate\Auth\AuthenticationException' => 'warning',
        'Illuminate\Auth\Access\AuthorizationException' => 'warning',
        'Illuminate\Database\Eloquent\ModelNotFoundException' => 'info',

        // Symfony HTTP Exceptions
        'Symfony\Component\HttpKernel\Exception\NotFoundHttpException' => 'info',
        'Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException' => 'warning',
        'Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException' => 'info',
        'Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException' => 'info',
        'Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException' => 'warning',
        'Symfony\Component\HttpKernel\Exception\BadRequestHttpException' => 'warning',

        // General HTTP Exception with closure
        'Symfony\Component\HttpKernel\Exception\HttpException' => function ($exception) {
            $statusCode = method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500;
            return $statusCode >= 500 ? 'error' : 'info';
        },

        // Default level for unmapped exceptions
        'default' => 'error',
    ],
];
```

### Customizing Exception Levels

You can customize how different exceptions are logged by modifying the `exception_levels` configuration:

```php
'exception_levels' => [
    // Skip logging certain exceptions entirely
    'App\Exceptions\IgnorableException' => 'skip',

    // Custom application exceptions
    'App\Exceptions\BusinessLogicException' => 'warning',
    'App\Exceptions\IntegrationException' => 'error',

    // Use closures for dynamic level determination
    'App\Exceptions\ConditionalException' => function ($exception) {
        return $exception->isCritical() ? 'error' : 'info';
    },

    // Default fallback
    'default' => 'error',
],
```

**Available levels:** `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug`, `skip`

Use `skip` to completely ignore certain exception types.

## Requirements

- PHP 8.0+
- Laravel 9.0+ | 10.0+ | 11.0+
- Guzzle HTTP client 7.0+

## Features

- ✅ Multiple log levels (info, error, warning, debug, custom)
- ✅ **Smart exception filtering** - Reduces noise from expected exceptions
- ✅ **Automatic severity detection** - ValidationException → info, 404s → info, 500s → error
- ✅ **Conditional logging** - `errorIf()` helper for cleaner code
- ✅ Structured context data with automatic exception details
- ✅ Configurable exception level mapping with closure support
- ✅ Automatic application and server information
- ✅ Webhook integration with retry logic
- ✅ Laravel logging fallback
- ✅ Configurable timeouts and headers
- ✅ Laravel service provider auto-discovery
- ✅ Facade support for easy usage

---

**Ready to centralize your logging!** 📊