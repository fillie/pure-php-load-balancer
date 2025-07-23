<?php

declare(strict_types=1);

use App\Clock\ClockInterface;
use App\Clock\SystemClock;
use App\Config\Config;
use App\Http\ResponseBuilder;
use App\LoadBalancer\LoadBalancerInterface;
use App\Server\RequestHandler;
use App\Server\ServerEventHandler;
use App\Server\ServerLogger;
use App\LoadBalancer\RoundRobinLoadBalancer;
use App\Logger\ConsoleLogger;
use App\Server\ServerInterface;
use App\Server\HttpServer;
use Psr\Log\LoggerInterface;

return function (): array {
    return [
        // Configuration
        Config::class => DI\factory(fn() => Config::fromEnv()),
        
        // Logger configuration
        LoggerInterface::class => DI\create(ConsoleLogger::class)
            ->constructor(
                DI\get('app.logging.enabled'),
                DI\get('app.logging.level')
            ),

        // Load balancer configuration
        LoadBalancerInterface::class => DI\create(RoundRobinLoadBalancer::class)
            ->constructor(DI\get('app.backend.servers')),

        // Clock
        ClockInterface::class => DI\create(SystemClock::class),
        
        // Response Builder
        ResponseBuilder::class => DI\create(ResponseBuilder::class)
            ->constructor(DI\get(ClockInterface::class)),
        
        // Server Logger
        ServerLogger::class => DI\create(ServerLogger::class)
            ->constructor(
                DI\get(LoggerInterface::class),
                DI\get('app.logging.enabled')
            ),
        
        // Event Handler
        ServerEventHandler::class => DI\create(ServerEventHandler::class)
            ->constructor(DI\get(LoggerInterface::class)),
        
        // Request Handler
        RequestHandler::class => DI\create(RequestHandler::class)
            ->constructor(
                DI\get(LoadBalancerInterface::class),
                DI\get(ResponseBuilder::class),
                DI\get(ServerLogger::class)
            ),
        
        // Server configuration
        ServerInterface::class => DI\create(HttpServer::class)
            ->constructor(
                DI\get(RequestHandler::class),
                DI\get(ServerEventHandler::class),
                DI\get(ServerLogger::class),
                DI\get(Config::class)
            ),

        // Parameters
        'app.server.host' => $_ENV['SERVER_HOST'] ?? '0.0.0.0',
        'app.server.port' => (int)($_ENV['SERVER_PORT'] ?? 9501),
        'app.backend.servers' => explode(',', $_ENV['DEFAULT_SERVERS'] ?? ''),
        'app.logging.enabled' => in_array(strtolower($_ENV['ENABLE_OUTPUT'] ?? 'true'), ['1', 'true', 'yes', 'on'], true),
        'app.logging.level' => $_ENV['LOG_LEVEL'] ?? 'info',
    ];
};