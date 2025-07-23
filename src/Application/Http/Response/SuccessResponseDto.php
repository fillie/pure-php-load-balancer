<?php

declare(strict_types=1);

namespace App\Application\Http\Response;

use App\Application\Http\Request\RequestMeta;
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
            'request_id' => $this->requestMeta->requestId,
            'target_server' => $this->targetServer,
            'data' => [
                'path' => $this->requestMeta->path,
                'method' => $this->requestMeta->method,
                'client_ip' => $this->requestMeta->clientIp
            ]
        ];
    }
}