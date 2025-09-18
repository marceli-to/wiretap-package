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

            // Add custom headers if configured
            if (!empty($this->config['webhook']['headers'])) {
                $headers = array_merge($headers, $this->config['webhook']['headers']);
            }

            $this->httpClient->post($webhookUrl, [
                'json' => $payload,
                'headers' => $headers,
            ]);

        } catch (RequestException $e) {
            // Silently fail webhook calls to prevent breaking the main application
            if ($this->config['webhook']['log_failures'] ?? true) {
                Log::warning('Wiretap webhook failed', [
                    'error' => $e->getMessage(),
                    'url' => $webhookUrl ?? 'not_set',
                ]);
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

            if (!empty($this->config['webhook']['headers'])) {
                $headers = array_merge($headers, $this->config['webhook']['headers']);
            }

            $this->httpClient->post($webhookUrl, [
                'json' => $payload,
                'headers' => $headers,
            ]);

        } catch (RequestException $e) {
            if ($this->config['webhook']['log_failures'] ?? true) {
                Log::warning('Wiretap custom webhook failed', [
                    'error' => $e->getMessage(),
                    'data' => $data,
                ]);
            }
        }
    }
}
