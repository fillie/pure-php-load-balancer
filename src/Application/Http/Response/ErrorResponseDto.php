<?php

declare(strict_types=1);

namespace App\Application\Http\Response;

use App\Application\Http\Request\RequestMeta;
use JsonSerializable;

readonly class ErrorResponseDto implements JsonSerializable
{
    public function __construct(
        public string $error,
        public ErrorCode $errorCode,
        public string $timestamp,
        public RequestMeta $requestMeta,
        public array $debugContext = []
    ) {
    }

    public function jsonSerialize(): array
    {
        $response = [
            'success' => false,
            'error' => $this->error,
            'error_code' => $this->errorCode->value,
            'timestamp' => $this->timestamp,
            'request_id' => $this->requestMeta->requestId,
            'data' => ['request' => $this->requestMeta->toArray()]
        ];

        // RFC 9457: Include structured debug fields when available
        if (!empty($this->debugContext)) {
            $response = array_merge($response, $this->debugContext);
        }

        return $response;
    }
}