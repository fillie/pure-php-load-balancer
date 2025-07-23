<?php

declare(strict_types=1);

namespace App\Tests\Application\Http\Response;

use App\Application\Http\Response\ErrorCode;
use App\Application\Http\Response\ErrorResponseDto;
use App\Application\Http\Request\RequestMeta;
use PHPUnit\Framework\TestCase;

class ErrorResponseDtoTest extends TestCase
{
    private RequestMeta $requestMeta;
    private ErrorResponseDto $dto;

    protected function setUp(): void
    {
        $this->requestMeta = new RequestMeta(
            'POST',
            '/api/error',
            '10.0.0.1',
            '2025-01-01 12:30:00',
            'error-request-123'
        );
        
        $this->dto = new ErrorResponseDto(
            'Internal server error',
            ErrorCode::INTERNAL_ERROR,
            '2025-01-01T12:30:00+00:00',
            $this->requestMeta
        );
    }

    public function testConstructorSetsProperties(): void
    {
        $this->assertEquals('Internal server error', $this->dto->error);
        $this->assertEquals(ErrorCode::INTERNAL_ERROR, $this->dto->errorCode);
        $this->assertEquals('2025-01-01T12:30:00+00:00', $this->dto->timestamp);
        $this->assertSame($this->requestMeta, $this->dto->requestMeta);
    }

    public function testJsonSerialize(): void
    {
        $expected = [
            'success' => false,
            'error' => 'Internal server error',
            'error_code' => 'INTERNAL_ERROR',
            'timestamp' => '2025-01-01T12:30:00+00:00',
            'request_id' => 'error-request-123',
            'data' => [
                'request' => [
                    'method' => 'POST',
                    'path' => '/api/error',
                    'client_ip' => '10.0.0.1',
                    'timestamp' => '2025-01-01 12:30:00',
                    'request_id' => 'error-request-123'
                ]
            ]
        ];

        $this->assertEquals($expected, $this->dto->jsonSerialize());
    }

    public function testJsonEncode(): void
    {
        $json = json_encode($this->dto);
        $decoded = json_decode($json, true);

        $this->assertFalse($decoded['success']);
        $this->assertEquals('Internal server error', $decoded['error']);
        $this->assertEquals('INTERNAL_ERROR', $decoded['error_code']);
        $this->assertEquals('2025-01-01T12:30:00+00:00', $decoded['timestamp']);
        $this->assertEquals('POST', $decoded['data']['request']['method']);
        $this->assertEquals('/api/error', $decoded['data']['request']['path']);
        $this->assertEquals('10.0.0.1', $decoded['data']['request']['client_ip']);
    }

    public function testServiceUnavailableError(): void
    {
        $serviceDto = new ErrorResponseDto(
            'No healthy servers available',
            ErrorCode::SERVICE_UNAVAILABLE,
            '2025-01-01T12:30:00+00:00',
            $this->requestMeta
        );

        $result = $serviceDto->jsonSerialize();
        $this->assertEquals('No healthy servers available', $result['error']);
        $this->assertEquals('SERVICE_UNAVAILABLE', $result['error_code']);
    }
}