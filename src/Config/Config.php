<?php

declare(strict_types=1);

namespace App\Config;

class Config
{
    public function __construct(private array $config = [])
    {
        $this->config = $config;
    }

    public static function fromEnv(): self
    {
        return new self([
            'app.env' => $_ENV['APP_ENV'] ?? 'production',
            'logging.enabled' => self::parseBoolean($_ENV['ENABLE_OUTPUT'] ?? 'true'),
            'server.host' => $_ENV['SERVER_HOST'] ?? '0.0.0.0',
            'server.port' => (int)($_ENV['SERVER_PORT'] ?? 9501),
            'backend.servers' => explode(',', $_ENV['DEFAULT_SERVERS'] ?? ''),
            'server.reload_async' => true,
            'server.max_wait_time' => 60,
        ]);
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

    private static function parseBoolean(string $value): bool
    {
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}