<?php

declare(strict_types=1);

namespace App\Support;

use App\Application\Http\Request\RequestMeta;
use App\Application\Http\Response\ErrorCode;
use App\Application\Http\Response\ErrorResponseDto;
use App\Application\Http\Response\SuccessResponseDto;
use App\Infrastructure\Clock\ClockInterface;
use App\Infrastructure\Config\Config;
use Throwable;

readonly class ResponseBuilder
{
    private const string MESSAGE_LOAD_BALANCER_WORKING = 'Load balancer is working';
    private const string MESSAGE_INTERNAL_SERVER_ERROR = 'Internal server error';
    
    public function __construct(
        private ClockInterface $clock,
        private Config $config
    ) {
    }
    
    public function buildSuccess(RequestMeta $requestMeta, string $targetServer): SuccessResponseDto
    {
        return new SuccessResponseDto(
            self::MESSAGE_LOAD_BALANCER_WORKING,
            $this->clock->now()->format('c'),
            $targetServer,
            $requestMeta
        );
    }
    
    public function buildServiceUnavailable(RequestMeta $requestMeta): ErrorResponseDto
    {
        return new ErrorResponseDto(
            'No healthy servers available',
            ErrorCode::SERVICE_UNAVAILABLE,
            $this->clock->now()->format('c'),
            $requestMeta
        );
    }
    
    public function buildInternalError(RequestMeta $requestMeta, ?Throwable $exception = null): ErrorResponseDto
    {
        return new ErrorResponseDto(
            self::MESSAGE_INTERNAL_SERVER_ERROR,
            ErrorCode::INTERNAL_ERROR,
            $this->clock->now()->format('c'),
            $requestMeta,
            $this->buildDebugContext($exception)
        );
    }

    /**
     * Build RFC 9457 structured error fields for debug mode
     */
    private function buildDebugContext(?Throwable $exception): array
    {
        if (!$this->config->isDebug() || $exception === null) {
            return [];
        }

        return [
            'debug' => [
                'exception_class' => get_class($exception),
                'exception_code' => $exception->getCode(),
                'exception_message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $this->formatStackTrace($exception)
            ]
        ];
    }

    /**
     * Format stack trace for structured output
     */
    private function formatStackTrace(Throwable $exception): array
    {
        $trace = [];
        foreach ($exception->getTrace() as $index => $frame) {
            $trace[] = [
                'index' => $index,
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'class' => $frame['class'] ?? null,
                'function' => $frame['function'] ?? 'unknown'
            ];
        }
        return $trace;
    }
}