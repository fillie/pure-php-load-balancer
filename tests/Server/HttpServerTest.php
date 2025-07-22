<?php

declare(strict_types=1);

namespace App\Tests\Server;

use App\Config\Config;
use App\Exception\NoHealthyServersException;
use App\LoadBalancer\LoadBalancerInterface;
use App\Server\HttpServer;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Http\Server;
use Psr\Log\LoggerInterface;

class HttpServerTest extends TestCase
{
    private LoadBalancerInterface|MockObject $loadBalancer;
    private HttpServer $httpServer;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->loadBalancer = $this->createMock(LoadBalancerInterface::class);
        $config = $this->createMock(Config::class);
        $logger = $this->createMock(LoggerInterface::class);
        $mockServer = $this->createMock(Server::class);
        
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
            $this->loadBalancer,
            $config,
            $logger,
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
        $this->loadBalancer
            ->expects($this->once())
            ->method('getNextServer')
            ->willReturn('http://localhost:8080');

        $request = $this->createMock(Request::class);
        $request->server = [
            'request_method' => 'GET',
            'request_uri' => '/test',
            'remote_addr' => '192.168.1.1'
        ];

        $response = $this->createMock(Response::class);
        $response->expects($this->once())->method('header')->with('Content-Type', 'application/json');
        $response->expects($this->once())->method('end');

        $this->httpServer->handleRequest($request, $response);
    }

    /**
     * @throws Exception
     */
    public function testHandleRequestWithDefaults(): void
    {
        $this->loadBalancer
            ->expects($this->once())
            ->method('getNextServer')
            ->willReturn('http://localhost:8080');

        $request = $this->createMock(Request::class);
        $request->server = [];

        $response = $this->createMock(Response::class);
        $response->expects($this->once())->method('header')->with('Content-Type', 'application/json');
        $response->expects($this->once())->method('end');

        $this->httpServer->handleRequest($request, $response);
    }

    /**
     * @throws Exception
     */
    public function testHandleRequestWithException(): void
    {
        $this->loadBalancer
            ->expects($this->once())
            ->method('getNextServer')
            ->willThrowException(new NoHealthyServersException());

        $request = $this->createMock(Request::class);
        $request->server = [
            'request_method' => 'POST',
            'request_uri' => '/api/test',
            'remote_addr' => '10.0.0.1'
        ];

        $response = $this->createMock(Response::class);
        $response->expects($this->once())->method('status')->with(503);
        $response->expects($this->once())->method('header')->with('Content-Type', 'application/json');
        $response->expects($this->once())->method('end');

        $this->httpServer->handleRequest($request, $response);
    }

    /**
     * @throws Exception
     */
    public function testHandleRequestResponseFormat(): void
    {
        $this->loadBalancer
            ->expects($this->once())
            ->method('getNextServer')
            ->willReturn('http://backend:3000');

        $request = $this->createMock(Request::class);
        $request->server = [
            'request_method' => 'PUT',
            'request_uri' => '/api/users/123',
            'remote_addr' => '172.16.0.1'
        ];

        $response = $this->createMock(Response::class);
        $response->expects($this->once())
            ->method('end')
            ->with($this->callback(function ($json) {
                $data = json_decode($json, true);
                return isset($data['success']) &&
                       isset($data['message']) && 
                       isset($data['target_server']) && 
                       isset($data['timestamp']) &&
                       isset($data['data']) &&
                       $data['success'] === true &&
                       $data['target_server'] === 'http://backend:3000' &&
                       $data['data']['path'] === '/api/users/123' &&
                       $data['data']['method'] === 'PUT';
            }));

        $this->httpServer->handleRequest($request, $response);
    }

    /**
     * @throws Exception
     */
    public function testHandleRequestErrorResponseFormat(): void
    {
        $errorMessage = 'Load balancer error';
        $this->loadBalancer
            ->expects($this->once())
            ->method('getNextServer')
            ->willThrowException(new \Exception($errorMessage));

        $request = $this->createMock(Request::class);
        $request->server = ['request_method' => 'GET', 'request_uri' => '/', 'remote_addr' => '127.0.0.1'];

        $response = $this->createMock(Response::class);
        $response->expects($this->once())
            ->method('end')
            ->with($this->callback(function ($json) {
                $data = json_decode($json, true);
                return isset($data['success']) &&
                       isset($data['error']) && 
                       isset($data['timestamp']) &&
                       $data['success'] === false &&
                       $data['error'] === 'Internal server error';
            }));

        $this->httpServer->handleRequest($request, $response);
    }
}