<?php

declare(strict_types=1);

namespace App\Application\Http\Request;

use App\Infrastructure\Clock\ClockInterface;
use OpenSwoole\Http\Request;

readonly class RequestMeta
{
    public function __construct(
        public string $method,
        public string $path,
        public string $clientIp,
        public string $timestamp,
        public string $requestId
    ) {
    }

    public static function fromSwooleRequest(Request $request, ClockInterface $clock, ?IpResolver $ipResolver = null): self
    {
        $server = $request->server ?? [];
        $headers = $request->header ?? [];
        
        // Extract or generate request ID for correlation tracking
        $requestId = $headers['x-request-id'] ?? self::generateRequestId();
        
        // Resolve client IP considering reverse proxy headers
        $clientIp = $ipResolver ? $ipResolver->resolveClientIp($request) : ($server['remote_addr'] ?? 'unknown');
        
        return new self(
            method: $server['request_method'] ?? 'GET',
            path: $server['request_uri'] ?? '/',
            clientIp: $clientIp,
            timestamp: $clock->now()->format('Y-m-d H:i:s'),
            requestId: $requestId
        );
    }

    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'path' => $this->path,
            'client_ip' => $this->clientIp,
            'timestamp' => $this->timestamp,
            'request_id' => $this->requestId
        ];
    }

    public function __toString(): string
    {
        return sprintf('[%s] %s %s %s %s', $this->requestId, $this->timestamp, $this->clientIp, $this->method, $this->path);
    }

    private static function generateRequestId(): string
    {
        return sprintf(
            '%08x-%04x-%04x-%04x-%012x',
            mt_rand(0, 0xffffffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffffffffffff)
        );
    }
}