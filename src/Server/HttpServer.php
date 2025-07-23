<?php

declare(strict_types=1);

namespace App\Server;

use App\Config\Config;
use App\Http\JsonResponse;
use App\Http\RequestMeta;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Http\Server;
use Psr\Log\LoggerInterface;

final class HttpServer implements ServerInterface
{
    
    private readonly Server $server;
    private readonly bool $logEnabled;
    private readonly bool $lifecycleHandlersEnabled;
    private readonly bool $isDevelopment;
    private readonly string $host;
    private readonly int $port;
    private bool $isBooted = false;

    public function __construct(
        private readonly RequestHandler $requestHandler,
        private readonly ServerEventHandler $eventHandler,
        private readonly ServerLogger $logger,
        private readonly Config $config,
        ?Server $server = null
    ) {
        $host = $this->config->string('server.host', '0.0.0.0');
        $port = $this->config->int('server.port', 9501);
        
        $this->server = $server ?? new Server($host, $port);
        $this->logEnabled = $this->config->bool('logging.enabled', true);
        $this->lifecycleHandlersEnabled = $this->config->bool('server.lifecycle_handlers.enabled', true);
        $this->isDevelopment = $this->config->isDevelopment();
        $this->host = $host;
        $this->port = $port;
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

    public function handleRequest(Request $req, Response $res): void
    {
        $requestMeta = RequestMeta::fromSwooleRequest($req);
        $jsonResponse = new JsonResponse($res);
        
        $this->requestHandler->handle($requestMeta, $jsonResponse);
    }
    

    public function start(): void
    {
        $this->boot();
        $this->logger->logServerStart($this->host, $this->port);
        $this->server->start();
    }

    public function stop(): void
    {
        $this->logger->logServerStop();
        $this->server->shutdown();
    }

    private function configureServer(): void
    {
        $serverConfig = $this->config->array('server.settings', []);
        
        // Apply development-specific defaults if not explicitly set
        if ($this->isDevelopment && !isset($serverConfig['reload_async'])) {
            $serverConfig['reload_async'] = true;
        }
        
        if (!empty($serverConfig)) {
            $this->server->set($serverConfig);
        }
    }

    private function setupEventHandlers(): void
    {
        if (!$this->lifecycleHandlersEnabled) {
            return;
        }

        // Use OpenSwoole native event handlers for proper async handling
        $this->server->on('shutdown', [$this->eventHandler, 'handleShutdown']);
        $this->server->on('workerStop', [$this->eventHandler, 'handleWorkerStop']);
        $this->server->on('managerStop', [$this->eventHandler, 'handleManagerStop']);
        $this->server->on('workerError', [$this->eventHandler, 'handleWorkerError']);
    }
}