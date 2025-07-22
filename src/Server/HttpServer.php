<?php

declare(strict_types=1);

namespace App\Server;

use App\LoadBalancer\LoadBalancerInterface;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Http\Server;

class HttpServer implements ServerInterface
{
    private Server $server;
    private LoadBalancerInterface $loadBalancer;

    public function __construct(
        LoadBalancerInterface $loadBalancer,
        string $host = '0.0.0.0',
        int $port = 9501,
        ?Server $server = null
    ) {
        $this->server = $server ?? new Server($host, $port);
        $this->loadBalancer = $loadBalancer;
        
        // Configure server based on environment
        $serverConfig = [];
        
        // Enable hot reload for development only
        if ($_ENV['APP_ENV'] === 'development') {
            $serverConfig['reload_async'] = true;
            $serverConfig['max_wait_time'] = 60;
        }
        
        if (!empty($serverConfig)) {
            $this->server->set($serverConfig);
        }
        
        $this->server->on('request', [$this, 'handleRequest']);
    }

    public function handleRequest(Request $req, Response $res): void
    {
        $method = $req->server['request_method'] ?? 'GET';
        $path = $req->server['request_uri'] ?? '/';
        $clientIp = $req->server['remote_addr'] ?? 'unknown';
        
        try {
            $targetServer = $this->loadBalancer->getNextServer();
            
            if (($_ENV['ENABLE_OUTPUT'] ?? 'true') === 'true') {
                echo sprintf("[%s] %s %s %s -> %s\n", 
                    date('Y-m-d H:i:s'), 
                    $clientIp,
                    $method, 
                    $path, 
                    $targetServer
                );
            }
            
            $res->header('Content-Type', 'application/json');
            $res->end(json_encode([
                'message' => 'Load balancer is working',
                'target_server' => $targetServer,
                'timestamp' => date('H:i:s'),
                'path' => $path,
                'method' => $method
            ]));
        } catch (\Exception $e) {
            if (($_ENV['ENABLE_OUTPUT'] ?? 'true') === 'true') {
                echo sprintf("[%s] %s %s %s -> ERROR: %s\n", 
                    date('Y-m-d H:i:s'), 
                    $clientIp, 
                    $method, 
                    $path, 
                    $e->getMessage()
                );
            }
            
            $res->status(500);
            $res->header('Content-Type', 'application/json');
            $res->end(json_encode([
                'error' => $e->getMessage(),
                'timestamp' => date('H:i:s')
            ]));
        }
    }

    public function start(): void
    {
        $this->server->start();
    }

    public function stop(): void
    {
        $this->server->shutdown();
    }
}