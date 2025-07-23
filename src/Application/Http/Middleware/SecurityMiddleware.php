<?php

declare(strict_types=1);

namespace App\Application\Http\Middleware;

use App\Infrastructure\Config\Config;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;

final class SecurityMiddleware
{
    private const string ERROR_REQUEST_TOO_LARGE = 'Request entity too large';
    private const string ERROR_RATE_LIMIT_EXCEEDED = 'Rate limit exceeded';
    
    private array $rateLimitBuckets = [];
    private readonly int $maxRequestSize;
    private readonly int $rateLimit;
    private readonly int $rateLimitWindow;
    private readonly bool $rateLimitEnabled;

    public function __construct(private readonly Config $config)
    {
        $this->maxRequestSize = $this->config->int('security.max_request_size', 1048576); // 1MB default
        $this->rateLimit = $this->config->int('security.rate_limit.requests', 100);
        $this->rateLimitWindow = $this->config->int('security.rate_limit.window', 60); // 60 seconds
        $this->rateLimitEnabled = $this->config->bool('security.rate_limit.enabled', true);
    }

    /**
     * Check if request passes security validation
     */
    public function validateRequest(Request $request, string $clientIp): ?array
    {
        // Check request size limit
        $sizeError = $this->checkRequestSize($request);
        if ($sizeError !== null) {
            return $sizeError;
        }

        // Check rate limiting
        if ($this->rateLimitEnabled) {
            $rateLimitError = $this->checkRateLimit($clientIp);
            if ($rateLimitError !== null) {
                return $rateLimitError;
            }
        }

        return null; // Request is valid
    }

    /**
     * Check request size against configured limit
     */
    private function checkRequestSize(Request $request): ?array
    {
        $contentLength = $request->header['content-length'] ?? 0;
        
        if ($contentLength > $this->maxRequestSize) {
            return [
                'error' => self::ERROR_REQUEST_TOO_LARGE,
                'status' => 413,
                'details' => [
                    'max_size' => $this->maxRequestSize,
                    'request_size' => $contentLength
                ]
            ];
        }

        return null;
    }

    /**
     * Check rate limit for client IP using sliding window
     */
    private function checkRateLimit(string $clientIp): ?array
    {
        $now = time();
        $windowStart = $now - $this->rateLimitWindow;

        // Initialize bucket if not exists
        if (!isset($this->rateLimitBuckets[$clientIp])) {
            $this->rateLimitBuckets[$clientIp] = [];
        }

        // Clean old requests outside the window
        $this->rateLimitBuckets[$clientIp] = array_filter(
            $this->rateLimitBuckets[$clientIp],
            fn(int $timestamp) => $timestamp > $windowStart
        );

        // Check if rate limit exceeded
        if (count($this->rateLimitBuckets[$clientIp]) >= $this->rateLimit) {
            return [
                'error' => self::ERROR_RATE_LIMIT_EXCEEDED,
                'status' => 429,
                'details' => [
                    'limit' => $this->rateLimit,
                    'window' => $this->rateLimitWindow,
                    'reset_time' => min($this->rateLimitBuckets[$clientIp]) + $this->rateLimitWindow
                ]
            ];
        }

        // Add current request to bucket
        $this->rateLimitBuckets[$clientIp][] = $now;

        return null;
    }

    /**
     * Send security error response
     */
    public function sendSecurityError(Response $response, array $error): void
    {
        $response->header('Content-Type', 'application/json');
        
        // Add rate limit headers if applicable
        if ($error['status'] === 429 && isset($error['details']['reset_time'])) {
            $response->header('Retry-After', (string)($error['details']['reset_time'] - time()));
            $response->header('X-RateLimit-Limit', (string)$this->rateLimit);
            $response->header('X-RateLimit-Window', (string)$this->rateLimitWindow);
        }

        $response->status($error['status']);
        $response->end(json_encode([
            'success' => false,
            'error' => $error['error'],
            'timestamp' => date('c'),
            'details' => $error['details'] ?? []
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Cleanup old rate limit entries (should be called periodically)
     */
    public function cleanup(): void
    {
        $now = time();
        $windowStart = $now - $this->rateLimitWindow;

        foreach ($this->rateLimitBuckets as $ip => $timestamps) {
            $this->rateLimitBuckets[$ip] = array_filter(
                $timestamps,
                fn(int $timestamp) => $timestamp > $windowStart
            );

            // Remove empty buckets
            if (empty($this->rateLimitBuckets[$ip])) {
                unset($this->rateLimitBuckets[$ip]);
            }
        }
    }
}