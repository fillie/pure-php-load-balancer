<?php

declare(strict_types=1);

namespace App\Application\Http\Response;

use App\Infrastructure\Clock\ClockInterface;
use JsonException;
use JsonSerializable;
use OpenSwoole\Http\Response;

readonly class JsonResponse
{
    public function __construct(
        private Response $response,
        private ClockInterface $clock,
        private ?string $requestId = null
    ) {
    }

    public function send(array|JsonSerializable $data, int $status = 200, array $headers = []): void
    {
        $this->response->status($status);
        $this->response->header('Content-Type', 'application/json');
        
        // Echo back request ID for correlation tracking
        if ($this->requestId !== null) {
            $this->response->header('X-Request-ID', $this->requestId);
        }
        
        foreach ($headers as $name => $value) {
            $this->response->header($name, $value);
        }

        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $this->response->end($json);
        } catch (JsonException $e) {
            $this->sendError('JSON encoding failed', 500);
        }
    }

    public function sendSuccess(string $message, array $data = [], ?string $targetServer = null): void
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'timestamp' => $this->clock->now()->format('c'),
            'data' => $data
        ];

        if ($this->requestId !== null) {
            $payload['request_id'] = $this->requestId;
        }

        if ($targetServer !== null) {
            $payload['target_server'] = $targetServer;
        }

        $this->send($payload);
    }

    public function sendError(string $message, int $status = 500, array $context = []): void
    {
        $payload = [
            'success' => false,
            'error' => $message,
            'timestamp' => $this->clock->now()->format('c'),
        ];

        if ($this->requestId !== null) {
            $payload['request_id'] = $this->requestId;
        }

        if (!empty($context)) {
            $payload['context'] = $context;
        }

        $this->send($payload, $status);
    }

    public function sendServiceUnavailable(string $message = 'No healthy servers available'): void
    {
        $this->sendError($message, 503);
    }

    public static function create(Response $response, ClockInterface $clock, ?string $requestId = null): self
    {
        return new self($response, $clock, $requestId);
    }
}