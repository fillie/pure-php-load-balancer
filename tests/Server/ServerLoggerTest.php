<?php

declare(strict_types=1);

namespace App\Tests\Server;

use App\Http\RequestMeta;
use App\Server\ServerLogger;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ServerLoggerTest extends TestCase
{
    private LoggerInterface $logger;
    private RequestMeta $requestMeta;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->requestMeta = new RequestMeta(
            'GET',
            '/api/test',
            '192.168.1.1',
            '2025-01-01 12:00:00'
        );
    }

    public function testLogRequestWhenLoggingEnabled(): void
    {
        $serverLogger = new ServerLogger($this->logger, true);
        $targetServer = 'http://localhost:8080';
        
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                '{request} -> {target}',
                [
                    'request' => '2025-01-01 12:00:00 192.168.1.1 GET /api/test',
                    'target' => $targetServer,
                    'method' => 'GET',
                    'path' => '/api/test',
                    'client_ip' => '192.168.1.1'
                ]
            );
            
        $serverLogger->logRequest($this->requestMeta, $targetServer);
    }

    public function testLogRequestWhenLoggingDisabled(): void
    {
        $serverLogger = new ServerLogger($this->logger, false);
        $targetServer = 'http://localhost:8080';
        
        $this->logger
            ->expects($this->never())
            ->method('info');
            
        $serverLogger->logRequest($this->requestMeta, $targetServer);
    }

    public function testLogErrorWhenLoggingEnabled(): void
    {
        $serverLogger = new ServerLogger($this->logger, true);
        $exception = new RuntimeException('Database connection failed');
        
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                '{request} -> ERROR: {message}',
                [
                    'request' => '2025-01-01 12:00:00 192.168.1.1 GET /api/test',
                    'message' => 'Database connection failed',
                    'exception' => $exception,
                    'method' => 'GET',
                    'path' => '/api/test',
                    'client_ip' => '192.168.1.1'
                ]
            );
            
        $serverLogger->logError($this->requestMeta, $exception);
    }

    public function testLogErrorWhenLoggingDisabled(): void
    {
        $serverLogger = new ServerLogger($this->logger, false);
        $exception = new Exception('Some error');
        
        $this->logger
            ->expects($this->never())
            ->method('error');
            
        $serverLogger->logError($this->requestMeta, $exception);
    }

    public function testLogServerStart(): void
    {
        $serverLogger = new ServerLogger($this->logger, true);
        
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Starting HTTP server',
                [
                    'host' => '0.0.0.0',
                    'port' => 9501
                ]
            );
            
        $serverLogger->logServerStart('0.0.0.0', 9501);
    }

    public function testLogServerStartWhenLoggingDisabled(): void
    {
        $serverLogger = new ServerLogger($this->logger, false);
        
        // Server start/stop should log regardless of logEnabled flag
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Starting HTTP server',
                [
                    'host' => '127.0.0.1',
                    'port' => 8080
                ]
            );
            
        $serverLogger->logServerStart('127.0.0.1', 8080);
    }

    public function testLogServerStop(): void
    {
        $serverLogger = new ServerLogger($this->logger, true);
        
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Stopping HTTP server');
            
        $serverLogger->logServerStop();
    }

    public function testLogServerStopWhenLoggingDisabled(): void
    {
        $serverLogger = new ServerLogger($this->logger, false);
        
        // Server start/stop should log regardless of logEnabled flag
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Stopping HTTP server');
            
        $serverLogger->logServerStop();
    }

    public function testLogRequestWithDifferentRequestMeta(): void
    {
        $serverLogger = new ServerLogger($this->logger, true);
        $differentMeta = new RequestMeta(
            'POST',
            '/api/users/create',
            '10.0.0.1',
            '2025-01-02 08:30:15'
        );
        
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                '{request} -> {target}',
                [
                    'request' => '2025-01-02 08:30:15 10.0.0.1 POST /api/users/create',
                    'target' => 'http://backend:3000',
                    'method' => 'POST',
                    'path' => '/api/users/create',
                    'client_ip' => '10.0.0.1'
                ]
            );
            
        $serverLogger->logRequest($differentMeta, 'http://backend:3000');
    }

    public function testLogErrorWithDifferentExceptionTypes(): void
    {
        $serverLogger = new ServerLogger($this->logger, true);
        
        $exceptions = [
            new RuntimeException('Runtime error'),
            new Exception('Generic exception'),
            new \InvalidArgumentException('Invalid argument')
        ];
        
        $this->logger
            ->expects($this->exactly(count($exceptions)))
            ->method('error')
            ->with(
                '{request} -> ERROR: {message}',
                $this->callback(function($context) {
                    return isset($context['request']) && 
                           isset($context['message']) && 
                           isset($context['exception']);
                })
            );
        
        foreach ($exceptions as $exception) {
            $serverLogger->logError($this->requestMeta, $exception);
        }
    }

    public function testLogTemplateConstants(): void
    {
        $serverLogger = new ServerLogger($this->logger, true);
        
        // Test that templates are used correctly
        $this->logger
            ->expects($this->exactly(2))
            ->method('info');
            
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('{request} -> ERROR: {message}', $this->isType('array'));
            
        $serverLogger->logRequest($this->requestMeta, 'http://test');
        $serverLogger->logServerStart('localhost', 9000);
        $serverLogger->logError($this->requestMeta, new Exception('test error'));
    }
}