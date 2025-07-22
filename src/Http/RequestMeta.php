<?php

declare(strict_types=1);

namespace App\Http;

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
        return new self(
            method: $request->server['request_method'] ?? 'GET',
            path: $request->server['request_uri'] ?? '/',
            clientIp: $request->server['remote_addr'] ?? 'unknown',
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