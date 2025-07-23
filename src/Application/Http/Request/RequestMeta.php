<?php

declare(strict_types=1);

namespace App\Application\Http\Request;

use OpenSwoole\Http\Request;

readonly class RequestMeta
{
    public function __construct(
        public string $method,
        public string $path,
        public string $clientIp,
        public string $timestamp
    ) {
    }

    public static function fromSwooleRequest(Request $request): self
    {
        $server = $request->server ?? [];
        
        return new self(
            method: $server['request_method'] ?? 'GET',
            path: $server['request_uri'] ?? '/',
            clientIp: $server['remote_addr'] ?? 'unknown',
            timestamp: date('Y-m-d H:i:s')
        );
    }

    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'path' => $this->path,
            'client_ip' => $this->clientIp,
            'timestamp' => $this->timestamp
        ];
    }

    public function __toString(): string
    {
        return sprintf('%s %s %s %s', $this->timestamp, $this->clientIp, $this->method, $this->path);
    }
}