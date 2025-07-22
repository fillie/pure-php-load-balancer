<?php

declare(strict_types=1);

namespace App\Server;

use App\Config\Config;
use App\Exception\NoHealthyServersException;
use App\Http\JsonResponse;
use App\Http\RequestMeta;
use App\LoadBalancer\LoadBalancerInterface;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Http\Server;
use Psr\Log\LoggerInterface;
use Throwable;

final class HttpServer implements ServerInterface
{
    private readonly Server $server;
    private readonly bool $logEnabled;
    private readonly bool $signalsEnabled;
    private bool $isBooted = false;

    public function __construct(
        private readonly LoadBalancerInterface $loadBalancer,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        ?Server $server = null
    ) {
        // Use Config as single source of truth for host/port
        $host = $this->config->string('server.host', '0.0.0.0');
        $port = $this->config->int('server.port', 9501);
        
        $this->server = $server ?? new Server($host, $port);
        $this->logEnabled = $this->config->bool('logging.enabled', true);
        $this->signalsEnabled = $this->config->bool('server.signals.enabled', true);
    }

    private function boot(): void
    {
        if ($this->isBooted) {
            return;
        }

        $this->configureServer();
        $this->server->on('request', [$this, 'handleRequest']);
        $this->setupEventHandlers();
        
        $this->isBooted = true;
    }

    /**
     * Handle incoming HTTP request and route to backend server
     * 
     * @param Request $req
     * @param Response $res
     * @return void
     */
    public function handleRequest(Request $req, Response $res): void
    {
        $requestMeta = RequestMeta::fromSwooleRequest($req);
        $jsonResponse = new JsonResponse($res);
        $timestamp = date('c');
        
        try {
            $targetServer = $this->loadBalancer->getNextServer();
            $this->logRequest($requestMeta, $targetServer);
            
            /** @var array{success: true, message: string, timestamp: string, target_server: string, data: array{path: string, method: string, client_ip: string}} $payload */
            $payload = [
                'success' => true,
                'message' => 'Load balancer is working',
                'timestamp' => $timestamp,
                'target_server' => $targetServer,
                'data' => [
                    'path' => $requestMeta->path,
                    'method' => $requestMeta->method,
                    'client_ip' => $requestMeta->clientIp
                ]
            ];
            
            $jsonResponse->send($payload);
        } catch (NoHealthyServersException $e) {
            $this->logError($requestMeta, $e);
            
            /** @var array{success: false, error: string, timestamp: string, data: array{request: array}} $payload */
            $payload = [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => $timestamp,
                'data' => ['request' => $requestMeta->toArray()]
            ];
            
            $jsonResponse->send($payload, 503);
        } catch (Throwable $e) {
            $this->logError($requestMeta, $e);
            
            /** @var array{success: false, error: string, timestamp: string, data: array{request: array}} $payload */
            $payload = [
                'success' => false,
                'error' => 'Internal server error',
                'timestamp' => $timestamp,
                'data' => ['request' => $requestMeta->toArray()]
            ];
            
            $jsonResponse->send($payload, 500);
        }
    }

    public function start(): void
    {
        $this->boot();
        $this->logger->info('Starting HTTP server', [
            'host' => $this->config->string('server.host'),
            'port' => $this->config->int('server.port')
        ]);
        $this->server->start();
    }

    public function stop(): void
    {
        $this->logger->info('Stopping HTTP server');
        $this->server->shutdown();
    }

    private function configureServer(): void
    {
        if (!$this->config->isDevelopment()) {
            return;
        }

        $serverConfig = [
            'reload_async' => $this->config->bool('server.reload_async', true),
            'max_wait_time' => $this->config->int('server.max_wait_time', 60),
        ];

        $this->server->set($serverConfig);
    }

    private function logRequest(RequestMeta $requestMeta, string $targetServer): void
    {
        if (!$this->logEnabled) {
            return;
        }

        $this->logger->info('{request} -> {target}', [
            'request' => (string)$requestMeta,
            'target' => $targetServer,
            'method' => $requestMeta->method,
            'path' => $requestMeta->path,
            'client_ip' => $requestMeta->clientIp
        ]);
    }

    private function logError(RequestMeta $requestMeta, Throwable $exception): void
    {
        if (!$this->logEnabled) {
            return;
        }

        $this->logger->error('{request} -> ERROR: {message}', [
            'request' => (string)$requestMeta,
            'message' => $exception->getMessage(),
            'exception' => $exception,
            'method' => $requestMeta->method,
            'path' => $requestMeta->path,
            'client_ip' => $requestMeta->clientIp
        ]);
    }


    private function setupEventHandlers(): void
    {
        if (!$this->signalsEnabled) {
            return;
        }

        // Use OpenSwoole native event handlers for proper async handling
        $this->server->on('shutdown', [$this, 'handleShutdown']);
        $this->server->on('workerStop', [$this, 'handleWorkerStop']);
    }

    public function handleShutdown(): void
    {
        $this->logger->info('Server shutdown initiated');
    }

    public function handleWorkerStop(): void
    {
        $this->logger->info('Worker stopping');
    }
}