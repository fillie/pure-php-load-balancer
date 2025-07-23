<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Http\Request\RequestMeta;
use App\Application\Http\Response\ErrorCode;
use App\Infrastructure\Clock\ClockInterface;
use App\Infrastructure\Config\Config;
use App\Support\ResponseBuilder;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ResponseBuilderTest extends TestCase
{
    private ClockInterface $clock;
    private Config $config;
    private ResponseBuilder $responseBuilder;
    private RequestMeta $requestMeta;

    protected function setUp(): void
    {
        $this->clock = $this->createMock(ClockInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->responseBuilder = new ResponseBuilder($this->clock, $this->config);
        
        $this->requestMeta = new RequestMeta(
            'GET',
            '/api/test',
            '192.168.1.1',
            '2025-01-01 12:00:00',
            'test-request-123'
        );
        
        $this->clock->method('now')->willReturn(new DateTimeImmutable('2025-01-01T12:30:00+00:00'));
        $this->config->method('isDebug')->willReturn(false);
    }

    public function testBuildSuccess(): void
    {
        $response = $this->responseBuilder->buildSuccess($this->requestMeta, 'http://localhost:8080');
        
        $this->assertEquals('Load balancer is working', $response->message);
        $this->assertEquals('2025-01-01T12:30:00+00:00', $response->timestamp);
        $this->assertEquals('http://localhost:8080', $response->targetServer);
        $this->assertSame($this->requestMeta, $response->requestMeta);
    }

    public function testBuildSuccessJsonStructure(): void
    {
        $response = $this->responseBuilder->buildSuccess($this->requestMeta, 'http://backend:3000');
        $json = $response->jsonSerialize();
        
        $expected = [
            'success' => true,
            'message' => 'Load balancer is working',
            'timestamp' => '2025-01-01T12:30:00+00:00',
            'request_id' => 'test-request-123',
            'target_server' => 'http://backend:3000',
            'data' => [
                'path' => '/api/test',
                'method' => 'GET',
                'client_ip' => '192.168.1.1'
            ]
        ];
        
        $this->assertEquals($expected, $json);
    }

    public function testBuildServiceUnavailable(): void
    {
        $response = $this->responseBuilder->buildServiceUnavailable($this->requestMeta);
        
        $this->assertEquals('No healthy servers available', $response->error);
        $this->assertEquals(ErrorCode::SERVICE_UNAVAILABLE, $response->errorCode);
        $this->assertEquals('2025-01-01T12:30:00+00:00', $response->timestamp);
        $this->assertSame($this->requestMeta, $response->requestMeta);
    }

    public function testBuildServiceUnavailableJsonStructure(): void
    {
        $response = $this->responseBuilder->buildServiceUnavailable($this->requestMeta);
        $json = $response->jsonSerialize();
        
        $expected = [
            'success' => false,
            'error' => 'No healthy servers available',
            'error_code' => 'SERVICE_UNAVAILABLE',
            'timestamp' => '2025-01-01T12:30:00+00:00',
            'request_id' => 'test-request-123',
            'data' => [
                'request' => [
                    'method' => 'GET',
                    'path' => '/api/test',
                    'client_ip' => '192.168.1.1',
                    'timestamp' => '2025-01-01 12:00:00',
                    'request_id' => 'test-request-123'
                ]
            ]
        ];
        
        $this->assertEquals($expected, $json);
    }

    public function testBuildInternalError(): void
    {
        $response = $this->responseBuilder->buildInternalError($this->requestMeta);
        
        $this->assertEquals('Internal server error', $response->error);
        $this->assertEquals(ErrorCode::INTERNAL_ERROR, $response->errorCode);
        $this->assertEquals('2025-01-01T12:30:00+00:00', $response->timestamp);
        $this->assertSame($this->requestMeta, $response->requestMeta);
    }

    public function testBuildInternalErrorJsonStructure(): void
    {
        $response = $this->responseBuilder->buildInternalError($this->requestMeta);
        $json = $response->jsonSerialize();
        
        $expected = [
            'success' => false,
            'error' => 'Internal server error',
            'error_code' => 'INTERNAL_ERROR',
            'timestamp' => '2025-01-01T12:30:00+00:00',
            'request_id' => 'test-request-123',
            'data' => [
                'request' => [
                    'method' => 'GET',
                    'path' => '/api/test',
                    'client_ip' => '192.168.1.1',
                    'timestamp' => '2025-01-01 12:00:00',
                    'request_id' => 'test-request-123'
                ]
            ]
        ];
        
        $this->assertEquals($expected, $json);
    }

    public function testClockIsUsedForTimestamp(): void
    {
        $fixedTime = new DateTimeImmutable('2025-02-15T14:30:45+00:00');
        
        // Create new builder with different clock mock
        $newClock = $this->createMock(ClockInterface::class);
        $newClock->method('now')->willReturn($fixedTime);
        $newConfig = $this->createMock(Config::class);
        $newConfig->method('isDebug')->willReturn(false);
        $newBuilder = new ResponseBuilder($newClock, $newConfig);
        
        $response = $newBuilder->buildSuccess($this->requestMeta, 'http://test');
        
        $this->assertEquals('2025-02-15T14:30:45+00:00', $response->timestamp);
    }

    public function testDifferentRequestMetaIsPreserved(): void
    {
        $differentMeta = new RequestMeta(
            'POST',
            '/api/users',
            '10.0.0.1',
            '2025-01-02 08:15:30',
            'different-request-456'
        );
        
        $response = $this->responseBuilder->buildSuccess($differentMeta, 'http://server:9000');
        
        $this->assertSame($differentMeta, $response->requestMeta);
        $this->assertEquals('http://server:9000', $response->targetServer);
    }
}