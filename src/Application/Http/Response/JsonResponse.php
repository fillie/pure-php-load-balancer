<?php

declare(strict_types=1);

namespace App\Application\Http\Response;

use JsonException;
use JsonSerializable;
use OpenSwoole\Http\Response;

readonly class JsonResponse
{
    public function __construct(private Response $response)
    {
    }

    public function send(array|JsonSerializable $data, int $status = 200, array $headers = []): void
    {
        $this->response->status($status);
        $this->response->header('Content-Type', 'application/json');
        
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
            'timestamp' => date('c'),
            'data' => $data
        ];

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
            'timestamp' => date('c'),
        ];

        if (!empty($context)) {
            $payload['context'] = $context;
        }

        $this->send($payload, $status);
    }

    public function sendServiceUnavailable(string $message = 'No healthy servers available'): void
    {
        $this->sendError($message, 503);
    }

    public static function create(Response $response): self
    {
        return new self($response);
    }
}