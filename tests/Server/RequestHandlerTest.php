<?php

declare(strict_types=1);

namespace App\Tests\Server;

use App\Exception\NoHealthyServersException;
use App\Http\ErrorCode;
use App\Http\ErrorResponseDto;
use App\Http\JsonResponse;
use App\Http\RequestMeta;
use App\Http\ResponseBuilder;
use App\Http\SuccessResponseDto;
use App\LoadBalancer\LoadBalancerInterface;
use App\Server\RequestHandler;
use App\Server\ServerLogger;
use Exception;
use PHPUnit\Framework\TestCase;

class RequestHandlerTest extends TestCase
{
    private LoadBalancerInterface $loadBalancer;
    private ResponseBuilder $responseBuilder;
    private ServerLogger $logger;
    private RequestHandler $requestHandler;
    private RequestMeta $requestMeta;
    private JsonResponse $jsonResponse;

    protected function setUp(): void
    {
        $this->loadBalancer = $this->createMock(LoadBalancerInterface::class);
        $this->responseBuilder = $this->createMock(ResponseBuilder::class);
        $this->logger = $this->createMock(ServerLogger::class);
        $this->jsonResponse = $this->createMock(JsonResponse::class);
        
        $this->requestHandler = new RequestHandler(
            $this->loadBalancer,
            $this->responseBuilder,
            $this->logger
        );
        
        $this->requestMeta = new RequestMeta(
            'GET',
            '/api/test',
            '192.168.1.1',
            '2025-01-01 12:00:00'
        );
    }

    public function testHandleSuccessfulRequest(): void
    {
        $targetServer = 'http://localhost:8080';
        $successResponse = $this->createMock(SuccessResponseDto::class);
        
        $this->loadBalancer
            ->expects($this->once())
            ->method('getNextServer')
            ->willReturn($targetServer);
            
        $this->logger
            ->expects($this->once())
            ->method('logRequest')
            ->with($this->requestMeta, $targetServer);
            
        $this->responseBuilder
            ->expects($this->once())
            ->method('buildSuccess')
            ->with($this->requestMeta, $targetServer)
            ->willReturn($successResponse);
            
        $this->jsonResponse
            ->expects($this->once())
            ->method('send')
            ->with($successResponse);
            
        $this->requestHandler->handle($this->requestMeta, $this->jsonResponse);
    }

    public function testHandleNoHealthyServersException(): void
    {
        $exception = new NoHealthyServersException('No servers available');
        $errorResponse = $this->createMock(ErrorResponseDto::class);
        
        $this->loadBalancer
            ->expects($this->once())
            ->method('getNextServer')
            ->willThrowException($exception);
            
        $this->logger
            ->expects($this->once())
            ->method('logError')
            ->with($this->requestMeta, $exception);
            
        $this->responseBuilder
            ->expects($this->once())
            ->method('buildServiceUnavailable')
            ->with($this->requestMeta)
            ->willReturn($errorResponse);
            
        $this->jsonResponse
            ->expects($this->once())
            ->method('send')
            ->with($errorResponse, 503);
            
        $this->requestHandler->handle($this->requestMeta, $this->jsonResponse);
    }

    public function testHandleGenericException(): void
    {
        $exception = new Exception('Database connection failed');
        $errorResponse = $this->createMock(ErrorResponseDto::class);
        
        $this->loadBalancer
            ->expects($this->once())
            ->method('getNextServer')
            ->willThrowException($exception);
            
        $this->logger
            ->expects($this->once())
            ->method('logError')
            ->with($this->requestMeta, $exception);
            
        $this->responseBuilder
            ->expects($this->once())
            ->method('buildInternalError')
            ->with($this->requestMeta)
            ->willReturn($errorResponse);
            
        $this->jsonResponse
            ->expects($this->once())
            ->method('send')
            ->with($errorResponse, 500);
            
        $this->requestHandler->handle($this->requestMeta, $this->jsonResponse);
    }

    public function testHandleWithDifferentTargetServers(): void
    {
        $targetServers = [
            'http://server1:8080',
            'http://server2:8081',
            'http://server3:8082'
        ];
        
        foreach ($targetServers as $targetServer) {
            $successResponse = $this->createMock(SuccessResponseDto::class);
            
            $this->loadBalancer
                ->expects($this->once())
                ->method('getNextServer')
                ->willReturn($targetServer);
                
            $this->logger
                ->expects($this->once())
                ->method('logRequest')
                ->with($this->requestMeta, $targetServer);
                
            $this->responseBuilder
                ->expects($this->once())
                ->method('buildSuccess')
                ->with($this->requestMeta, $targetServer)
                ->willReturn($successResponse);
                
            $this->jsonResponse
                ->expects($this->once())
                ->method('send')
                ->with($successResponse);
                
            // Create new handler for each test to reset mocks
            $handler = new RequestHandler(
                $this->loadBalancer,
                $this->responseBuilder,
                $this->logger
            );
            
            $handler->handle($this->requestMeta, $this->jsonResponse);
            
            // Reset mocks for next iteration
            $this->loadBalancer = $this->createMock(LoadBalancerInterface::class);
            $this->responseBuilder = $this->createMock(ResponseBuilder::class);
            $this->logger = $this->createMock(ServerLogger::class);
            $this->jsonResponse = $this->createMock(JsonResponse::class);
        }
        
        $this->assertTrue(true); // Assert test completed without errors
    }

    public function testHandleLogsRequestBeforeResponse(): void
    {
        $targetServer = 'http://localhost:8080';
        $successResponse = $this->createMock(SuccessResponseDto::class);
        
        // Track call order
        $callOrder = [];
        
        $this->loadBalancer
            ->expects($this->once())
            ->method('getNextServer')
            ->willReturn($targetServer);
            
        $this->logger
            ->expects($this->once())
            ->method('logRequest')
            ->with($this->requestMeta, $targetServer)
            ->willReturnCallback(function() use (&$callOrder) {
                $callOrder[] = 'log';
            });
            
        $this->responseBuilder
            ->expects($this->once())
            ->method('buildSuccess')
            ->with($this->requestMeta, $targetServer)
            ->willReturn($successResponse);
            
        $this->jsonResponse
            ->expects($this->once())
            ->method('send')
            ->with($successResponse)
            ->willReturnCallback(function() use (&$callOrder) {
                $callOrder[] = 'send';
            });
            
        $this->requestHandler->handle($this->requestMeta, $this->jsonResponse);
        
        $this->assertEquals(['log', 'send'], $callOrder);
    }

    public function testHandleDoesNotLogOnSuccessfulExecution(): void
    {
        $targetServer = 'http://localhost:8080';
        $successResponse = $this->createMock(SuccessResponseDto::class);
        
        $this->loadBalancer
            ->expects($this->once())
            ->method('getNextServer')
            ->willReturn($targetServer);
            
        $this->logger
            ->expects($this->once())
            ->method('logRequest')
            ->with($this->requestMeta, $targetServer);
            
        // Should NOT call logError
        $this->logger
            ->expects($this->never())
            ->method('logError');
            
        $this->responseBuilder
            ->expects($this->once())
            ->method('buildSuccess')
            ->with($this->requestMeta, $targetServer)
            ->willReturn($successResponse);
            
        $this->jsonResponse
            ->expects($this->once())
            ->method('send')
            ->with($successResponse);
            
        $this->requestHandler->handle($this->requestMeta, $this->jsonResponse);
    }
}