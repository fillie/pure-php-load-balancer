<?php

declare(strict_types=1);

namespace App\Http;

use App\Clock\ClockInterface;

readonly class ResponseBuilder
{
    private const string MESSAGE_LOAD_BALANCER_WORKING = 'Load balancer is working';
    private const string MESSAGE_INTERNAL_SERVER_ERROR = 'Internal server error';
    
    public function __construct(
        private ClockInterface $clock
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
    
    public function buildInternalError(RequestMeta $requestMeta): ErrorResponseDto
    {
        return new ErrorResponseDto(
            self::MESSAGE_INTERNAL_SERVER_ERROR,
            ErrorCode::INTERNAL_ERROR,
            $this->clock->now()->format('c'),
            $requestMeta
        );
    }
}