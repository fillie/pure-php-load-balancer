<?php

declare(strict_types=1);

namespace App\Http;

use JsonSerializable;

readonly class ErrorResponseDto implements JsonSerializable
{
    public function __construct(
        public string $error,
        public ErrorCode $errorCode,
        public string $timestamp,
        public RequestMeta $requestMeta
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'success' => false,
            'error' => $this->error,
            'error_code' => $this->errorCode->value,
            'timestamp' => $this->timestamp,
            'data' => ['request' => $this->requestMeta->toArray()]
        ];
    }
}