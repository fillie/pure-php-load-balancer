<?php

declare(strict_types=1);

namespace App\Http;

use JsonSerializable;

readonly class SuccessResponseDto implements JsonSerializable
{
    public function __construct(
        public string $message,
        public string $timestamp,
        public string $targetServer,
        public RequestMeta $requestMeta
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'success' => true,
            'message' => $this->message,
            'timestamp' => $this->timestamp,
            'target_server' => $this->targetServer,
            'data' => [
                'path' => $this->requestMeta->path,
                'method' => $this->requestMeta->method,
                'client_ip' => $this->requestMeta->clientIp
            ]
        ];
    }
}