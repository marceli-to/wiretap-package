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

# Application name (will appear in dashboard)
WIRETAP_APP_NAME="Your Application Name"

# Optional: Also log to Laravel's default log files
WIRETAP_LARAVEL_ENABLED=true

# Optional: Log webhook failures to Laravel logs
WIRETAP_WEBHOOK_LOG_FAILURES=true

# Optional: HTTP timeout for webhook requests (seconds)
WIRETAP_TIMEOUT=10
```

### Custom Headers (Optional)

If your webhook endpoint requires authentication, edit `config/wiretap.php`:

```php
'webhook' => [
    'headers' => [
        'Authorization' => 'Bearer your-secret-token',
        'X-API-Key' => env('WIRETAP_API_KEY'),
    ],
],
```

Then add to `.env`:
```env
WIRETAP_API_KEY=your-secret-api-key
```

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

        // Custom headers for authentication
        'headers' => [
            // 'Authorization' => 'Bearer token',
        ],

        // Log webhook failures
        'log_failures' => env('WIRETAP_WEBHOOK_LOG_FAILURES', true),
    ],

    // HTTP timeout for webhook requests
    'timeout' => env('WIRETAP_TIMEOUT', 5),
];
```

## Requirements

- PHP 8.0+
- Laravel 9.0+ | 10.0+ | 11.0+
- Guzzle HTTP client 7.0+

## Features

- ✅ Multiple log levels (info, error, warning, debug, custom)
- ✅ Structured context data
- ✅ Automatic application and server information
- ✅ Webhook integration with retry logic
- ✅ Laravel logging fallback
- ✅ Configurable timeouts and headers
- ✅ Laravel service provider auto-discovery
- ✅ Facade support for easy usage

---

**Ready to centralize your logging!** 📊