<?php

declare(strict_types=1);

namespace App\Server;

use App\Http\RequestMeta;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class ServerLogger
{
    private const string LOG_REQUEST_TEMPLATE = '{request} -> {target}';
    private const string LOG_ERROR_TEMPLATE = '{request} -> ERROR: {message}';
    
    public function __construct(
        private LoggerInterface $logger,
        private bool $logEnabled
    ) {
    }
    
    public function logRequest(RequestMeta $requestMeta, string $targetServer): void
    {
        if (!$this->logEnabled) {
            return;
        }

        $this->logger->info(self::LOG_REQUEST_TEMPLATE, [
            'request' => (string)$requestMeta,
            'target' => $targetServer,
            'method' => $requestMeta->method,
            'path' => $requestMeta->path,
            'client_ip' => $requestMeta->clientIp
        ]);
    }

    public function logError(RequestMeta $requestMeta, Throwable $exception): void
    {
        if (!$this->logEnabled) {
            return;
        }

        $this->logger->error(self::LOG_ERROR_TEMPLATE, [
            'request' => (string)$requestMeta,
            'message' => $exception->getMessage(),
            'exception' => $exception,
            'method' => $requestMeta->method,
            'path' => $requestMeta->path,
            'client_ip' => $requestMeta->clientIp
        ]);
    }
    
    public function logServerStart(string $host, int $port): void
    {
        $this->logger->info('Starting HTTP server', [
            'host' => $host,
            'port' => $port
        ]);
    }
    
    public function logServerStop(): void
    {
        $this->logger->info('Stopping HTTP server');
    }
}