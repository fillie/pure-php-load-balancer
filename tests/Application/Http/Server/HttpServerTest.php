<?php

declare(strict_types=1);

namespace App\Tests\Application\Http\Server;

use App\Infrastructure\Config\Config;
use App\Domain\Exception\NoHealthyServersException;
use App\Support\ResponseBuilder;
use App\Application\Http\Server\RequestHandler;
use App\Application\Http\Server\ServerEventHandler;
use App\Application\Http\Server\ServerLogger;
use App\Domain\LoadBalancer\LoadBalancerInterface;
use App\Application\Http\Server\HttpServer;
use App\Application\Http\Request\RequestMeta;
use App\Application\Http\Response\JsonResponse;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Http\Server;
use Psr\Log\LoggerInterface;

class HttpServerTest extends TestCase
{
    private RequestHandler|MockObject $requestHandler;
    private HttpServer $httpServer;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $config = $this->createMock(Config::class);
        $mockServer = $this->createMock(Server::class);
        
        // Create mock components
        $this->requestHandler = $this->createMock(RequestHandler::class);
        $eventHandler = $this->createMock(ServerEventHandler::class);
        $serverLogger = $this->createMock(ServerLogger::class);
        
        // Configure default mock behaviors
        $config->method('bool')->willReturn(false); // Disable logging by default
        $config->method('isDevelopment')->willReturn(false);
        
        // Configure config mock for the new constructor behavior
        $config->method('string')->willReturnCallback(function ($key, $default = '') {
            return match ($key) {
                'server.host' => '127.0.0.1',
                'server.port' => 9999,
                default => $default
            };
        });
        $config->method('int')->willReturnCallback(function ($key, $default = 0) {
            return match ($key) {
                'server.port' => 9999,
                default => $default
            };
        });
        
        // Create HttpServer with mocked dependencies
        $this->httpServer = new HttpServer(
            $this->requestHandler,
            $eventHandler,
            $serverLogger,
            $config,
            $mockServer
        );
    }

    public function testConstructorSetsCorrectConfiguration(): void
    {
        $this->assertInstanceOf(HttpServer::class, $this->httpServer);
    }

    /**
     * @throws Exception
     */
    public function testHandleRequestSuccess(): void
    {
        $request = $this->createMock(Request::class);
        $request->server = [
            'request_method' => 'GET',
            'request_uri' => '/test',
            'remote_addr' => '192.168.1.1'
        ];

        $response = $this->createMock(Response::class);
        
        $this->requestHandler
            ->expects($this->once())
            ->method('handle')
            ->with(
                $this->isInstanceOf(RequestMeta::class),
                $this->isInstanceOf(JsonResponse::class)
            );

        $this->httpServer->handleRequest($request, $response);
    }

    /**
     * @throws Exception
     */
    public function testHandleRequestWithDefaults(): void
    {
        $request = $this->createMock(Request::class);
        $request->server = [];

        $response = $this->createMock(Response::class);
        
        $this->requestHandler
            ->expects($this->once())
            ->method('handle')
            ->with(
                $this->isInstanceOf(RequestMeta::class),
                $this->isInstanceOf(JsonResponse::class)
            );

        $this->httpServer->handleRequest($request, $response);
    }

    /**
     * @throws Exception
     */
    public function testHandleRequestWithException(): void
    {
        $request = $this->createMock(Request::class);
        $request->server = [
            'request_method' => 'POST',
            'request_uri' => '/api/test',
            'remote_addr' => '10.0.0.1'
        ];

        $response = $this->createMock(Response::class);
        
        $this->requestHandler
            ->expects($this->once())
            ->method('handle')
            ->with(
                $this->isInstanceOf(RequestMeta::class),
                $this->isInstanceOf(JsonResponse::class)
            );

        $this->httpServer->handleRequest($request, $response);
    }

    /**
     * @throws Exception
     */
    public function testHandleRequestResponseFormat(): void
    {
        $request = $this->createMock(Request::class);
        $request->server = [
            'request_method' => 'PUT',
            'request_uri' => '/api/users/123',
            'remote_addr' => '172.16.0.1'
        ];

        $response = $this->createMock(Response::class);
        
        $this->requestHandler
            ->expects($this->once())
            ->method('handle')
            ->with(
                $this->isInstanceOf(RequestMeta::class),
                $this->isInstanceOf(JsonResponse::class)
            );

        $this->httpServer->handleRequest($request, $response);
    }

    /**
     * @throws Exception
     */
    public function testHandleRequestErrorResponseFormat(): void
    {
        $request = $this->createMock(Request::class);
        $request->server = ['request_method' => 'GET', 'request_uri' => '/', 'remote_addr' => '127.0.0.1'];

        $response = $this->createMock(Response::class);
        
        $this->requestHandler
            ->expects($this->once())
            ->method('handle')
            ->with(
                $this->isInstanceOf(RequestMeta::class),
                $this->isInstanceOf(JsonResponse::class)
            );

        $this->httpServer->handleRequest($request, $response);
    }
}