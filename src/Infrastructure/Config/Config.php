<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

use Symfony\Component\Config\Definition\Processor;

readonly class Config
{
    public function __construct(private array $config = [])
    {
    }

    public static function fromEnv(): self
    {
        $env = $_ENV['APP_ENV'] ?? 'production';
        
        // Build raw configuration from environment variables
        $rawConfig = [
            'app' => [
                'env' => $env,
                'debug' => ConfigDefinition::parseBoolean($_ENV['APP_DEBUG'] ?? ($env === 'development' ? 'true' : 'false')),
            ],
            'logging' => [
                'enabled' => ConfigDefinition::parseBoolean($_ENV['ENABLE_OUTPUT'] ?? 'true'),
            ],
            'server' => [
                'host' => $_ENV['SERVER_HOST'] ?? '0.0.0.0',
                'port' => (int)($_ENV['SERVER_PORT'] ?? 9501),
                'lifecycle_handlers' => [
                    'enabled' => true,
                ],
                'settings' => [
                    'reload_async' => true,
                    'max_wait_time' => 60,
                ],
            ],
            'backend' => [
                'servers' => ConfigDefinition::parseServerList($_ENV['DEFAULT_SERVERS'] ?? ''),
            ],
            'security' => [
                'max_request_size' => (int)($_ENV['MAX_REQUEST_SIZE'] ?? 1048576),
                'rate_limit' => [
                    'enabled' => ConfigDefinition::parseBoolean($_ENV['RATE_LIMIT_ENABLED'] ?? 'true'),
                    'requests' => (int)($_ENV['RATE_LIMIT_REQUESTS'] ?? 100),
                    'window' => (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 60),
                ],
                'trusted_proxies' => ConfigDefinition::parseServerList($_ENV['TRUSTED_PROXIES'] ?? ''),
                'forwarded_header' => $_ENV['FORWARDED_HEADER'] ?? 'x-forwarded-for',
                'trust_forwarded_headers' => ConfigDefinition::parseBoolean($_ENV['TRUST_FORWARDED_HEADERS'] ?? 'false'),
            ],
        ];

        // Process and validate configuration using symfony/config
        $processor = new Processor();
        $configDefinition = new ConfigDefinition();
        $processedConfig = $processor->processConfiguration($configDefinition, [$rawConfig]);

        // Flatten the configuration for backward compatibility
        $flatConfig = [
            'app.env' => $processedConfig['app']['env'],
            'app.debug' => $processedConfig['app']['debug'],
            'logging.enabled' => $processedConfig['logging']['enabled'],
            'server.host' => $processedConfig['server']['host'],
            'server.port' => $processedConfig['server']['port'],
            'backend.servers' => $processedConfig['backend']['servers'],
            'server.lifecycle_handlers.enabled' => $processedConfig['server']['lifecycle_handlers']['enabled'],
            'server.settings.reload_async' => $processedConfig['server']['settings']['reload_async'],
            'server.settings.max_wait_time' => $processedConfig['server']['settings']['max_wait_time'],
            'security.max_request_size' => $processedConfig['security']['max_request_size'],
            'security.rate_limit.enabled' => $processedConfig['security']['rate_limit']['enabled'],
            'security.rate_limit.requests' => $processedConfig['security']['rate_limit']['requests'],
            'security.rate_limit.window' => $processedConfig['security']['rate_limit']['window'],
            'security.trusted_proxies' => $processedConfig['security']['trusted_proxies'],
            'security.forwarded_header' => $processedConfig['security']['forwarded_header'],
            'security.trust_forwarded_headers' => $processedConfig['security']['trust_forwarded_headers'],
        ];

        return new self($flatConfig);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int)$this->get($key, $default);
    }

    public function string(string $key, string $default = ''): string
    {
        return (string)$this->get($key, $default);
    }

    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);
        return is_array($value) ? $value : $default;
    }

    public function isDevelopment(): bool
    {
        return $this->string('app.env') === 'development';
    }

    public function isDebug(): bool
    {
        return $this->bool('app.debug');
    }
}