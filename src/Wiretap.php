<?php

namespace MarceliTo\Wiretap;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class Wiretap
{
    protected $config;
    protected $httpClient;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->httpClient = new Client([
            'timeout' => $config['timeout'] ?? 5,
        ]);
    }

    /**
     * Log an info message and optionally send to webhook
     */
    public function info(string $message, array $context = [], bool $sendWebhook = true): void
    {
        $this->log('info', $message, $context, $sendWebhook);
    }

    /**
     * Log an error message and optionally send to webhook
     */
    public function error(string $message, array $context = [], bool $sendWebhook = true): void
    {
        $this->log('error', $message, $context, $sendWebhook);
    }

    /**
     * Log a warning message and optionally send to webhook
     */
    public function warning(string $message, array $context = [], bool $sendWebhook = true): void
    {
        $this->log('warning', $message, $context, $sendWebhook);
    }

    /**
     * Log a debug message and optionally send to webhook
     */
    public function debug(string $message, array $context = [], bool $sendWebhook = false): void
    {
        $this->log('debug', $message, $context, $sendWebhook);
    }

    /**
     * Log a custom event and optionally send to webhook
     */
    public function event(string $event, array $data = [], bool $sendWebhook = true): void
    {
        $this->log('event', "Event: {$event}", array_merge(['event' => $event], $data), $sendWebhook);
    }

    /**
     * Log an exception with automatic level detection based on configuration
     */
    public function exception(\Throwable $exception, array $context = [], bool $sendWebhook = true): void
    {
        $level = $this->determineExceptionLevel($exception);

        if ($level === 'skip') {
            return;
        }

        $message = $exception->getMessage() ?: get_class($exception);
        $exceptionContext = array_merge([
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ], $context);

        $this->log($level, "Exception: {$message}", $exceptionContext, $sendWebhook);
    }

    /**
     * Conditionally log an error message based on a condition
     */
    public function errorIf(bool $condition, string $message, array $context = [], bool $sendWebhook = true): void
    {
        if ($condition) {
            $this->error($message, $context, $sendWebhook);
        }
    }

    /**
     * Determine the appropriate log level for an exception based on configuration
     */
    protected function determineExceptionLevel(\Throwable $exception): string
    {
        $exceptionLevels = $this->config['exception_levels'] ?? [];
        $exceptionClass = get_class($exception);

        // Check for exact class match first
        if (isset($exceptionLevels[$exceptionClass])) {
            $level = $exceptionLevels[$exceptionClass];

            // Handle closures (but not strings that might be function names)
            if (is_callable($level) && !is_string($level)) {
                $result = $level($exception);
                return $result ?? 'error';
            }

            return $level;
        }

        // Check for parent class matches
        foreach ($exceptionLevels as $configuredClass => $level) {
            if ($configuredClass !== 'default' && is_a($exception, $configuredClass)) {
                // Handle closures (but not strings that might be function names)
                if (is_callable($level) && !is_string($level)) {
                    $result = $level($exception);
                    return $result ?? 'error';
                }

                return $level;
            }
        }

        // Return default level
        return $exceptionLevels['default'] ?? 'error';
    }

    /**
     * Main logging method
     */
    protected function log(string $level, string $message, array $context = [], bool $sendWebhook = true): void
    {
        // Check if Wiretap is globally enabled
        if (!($this->config['enabled'] ?? true)) {
            return;
        }

        // Log to Laravel's default logger
        if ($this->config['log_to_laravel'] ?? true) {
            Log::{$level}($message, $context);
        }

        // Send to webhook if enabled
        if ($sendWebhook && ($this->config['webhook']['enabled'] ?? false)) {
            $this->sendToWebhook($level, $message, $context);
        }
    }

    /**
     * Send data to webhook
     */
    protected function sendToWebhook(string $level, string $message, array $context = []): void
    {
        try {
            $webhookUrl = $this->config['webhook']['url'] ?? null;

            if (!$webhookUrl) {
                return;
            }

            $payload = [
                'timestamp' => now()->toISOString(),
                'level' => $level,
                'message' => $message,
                'context' => $context,
                'app' => [
                    'name' => config('app.name', 'Laravel'),
                    'env' => config('app.env', 'production'),
                    'url' => config('app.url', ''),
                ],
                'server' => [
                    'hostname' => gethostname(),
                    'ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
                ],
            ];

            $headers = [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Wiretap/1.0',
            ];

            // Add authentication header if secret is configured
            if ($secret = $this->config['webhook']['secret'] ?? null) {
                $headers['Authorization'] = 'Bearer ' . $secret;
            }

            // Add custom headers if configured
            if (!empty($this->config['webhook']['headers'])) {
                $headers = array_merge($headers, $this->config['webhook']['headers']);
            }

            $response = $this->httpClient->post($webhookUrl, [
                'json' => $payload,
                'headers' => $headers,
            ]);

        } catch (RequestException $e) {
            // Silently fail webhook calls to prevent breaking the main application
            if ($this->config['webhook']['log_failures'] ?? true) {
                $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;

                if ($statusCode === 401) {
                    Log::warning('Wiretap webhook authentication failed. Please check WIRETAP_WEBHOOK_SECRET configuration.', [
                        'error' => $e->getMessage(),
                        'url' => $webhookUrl ?? 'not_set',
                        'status_code' => $statusCode,
                    ]);
                } else {
                    Log::warning('Wiretap webhook failed', [
                        'error' => $e->getMessage(),
                        'url' => $webhookUrl ?? 'not_set',
                        'status_code' => $statusCode,
                    ]);
                }
            }
        }
    }

    /**
     * Send custom data directly to webhook
     */
    public function sendWebhook(array $data): void
    {
        // Check if Wiretap is globally enabled
        if (!($this->config['enabled'] ?? true)) {
            return;
        }

        if (!($this->config['webhook']['enabled'] ?? false)) {
            return;
        }

        $webhookUrl = $this->config['webhook']['url'] ?? null;

        if (!$webhookUrl) {
            return;
        }

        try {
            $payload = array_merge([
                'timestamp' => now()->toISOString(),
                'type' => 'custom',
                'app' => [
                    'name' => config('app.name', 'Laravel'),
                    'env' => config('app.env', 'production'),
                ],
            ], $data);

            $headers = [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Wiretap/1.0',
            ];

            // Add authentication header if secret is configured
            if ($secret = $this->config['webhook']['secret'] ?? null) {
                $headers['Authorization'] = 'Bearer ' . $secret;
            }

            if (!empty($this->config['webhook']['headers'])) {
                $headers = array_merge($headers, $this->config['webhook']['headers']);
            }

            $response = $this->httpClient->post($webhookUrl, [
                'json' => $payload,
                'headers' => $headers,
            ]);

        } catch (RequestException $e) {
            if ($this->config['webhook']['log_failures'] ?? true) {
                $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;

                if ($statusCode === 401) {
                    Log::warning('Wiretap webhook authentication failed. Please check WIRETAP_WEBHOOK_SECRET configuration.', [
                        'error' => $e->getMessage(),
                        'data' => $data,
                        'status_code' => $statusCode,
                    ]);
                } else {
                    Log::warning('Wiretap custom webhook failed', [
                        'error' => $e->getMessage(),
                        'data' => $data,
                        'status_code' => $statusCode,
                    ]);
                }
            }
        }
    }
}
