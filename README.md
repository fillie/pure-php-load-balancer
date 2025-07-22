# PHP Load Balancer

A simple PHP load balancer built with OpenSwoole and Symfony Dependency Injection.

## Features

- **Round-robin load balancing** - Distributes requests evenly across backend servers
- **Hot reload in development** - Automatic code reloading when files change
- **Environment-based configuration** - Uses .env files for configuration
- **Comprehensive testing** - PHPUnit tests with proper mocking
- **Docker support** - Containerized development environment

## Quick Start

1. **Clone and start the load balancer:**
   ```bash
   git clone <repository>
   cd php-load-balancer
   ./scripts/start.sh
   ```

2. **Access the load balancer:**
   ```
   http://localhost:9501
   ```

## Scripts

- `./scripts/start.sh` - Start the load balancer (with cache)
- `./scripts/start.sh --no-cache` - Start with fresh build (for new dependencies)
- `./scripts/start.sh -v` - Start with verbose output
- `./scripts/test.sh` - Run tests
- `./scripts/test.sh -v` - Run tests with verbose output

## Configuration

Configuration is managed via `.env` files:

### Environment Variables

- `APP_ENV` - Application environment (`development`, `production`, `testing`)
- `SERVER_HOST` - Server host (default: `0.0.0.0`)
- `SERVER_PORT` - Server port (default: `9501`)
- `ENABLE_OUTPUT` - Enable request logging (`true`, `false`)
- `DEFAULT_SERVERS` - Comma-separated list of backend servers

An example `.env` file has been provided.

## Development

### Hot Reload

Hot reload is automatically enabled in development mode:
- Edit files in `src/` directory
- Changes are automatically detected and reloaded
- No need to restart the container

### Adding Dependencies

When adding new Composer dependencies:
```bash
# Add to composer.json, then rebuild
./scripts/start.sh --no-cache
```

### Testing

Run the test suite:
```bash
./scripts/test.sh
```

Tests use a separate `.env.testing` configuration with output disabled for clean test results.

## Architecture

### Load Balancer

- **`LoadBalancerInterface`** - Contract for load balancing algorithms
- **`RoundRobinLoadBalancer`** - Round-robin implementation

### HTTP Server

- **`ServerInterface`** - Contract for HTTP servers
- **`HttpServer`** - OpenSwoole-based HTTP server implementation

### Configuration

- **`bootstrap.php`** - Application bootstrapping and dependency injection
- **`services.yaml`** - Symfony DI container configuration

## API

### Load Balancer Response

```json
{
  "message": "Load balancer is working",
  "target_server": "http://localhost:8080",
  "timestamp": "14:30:15",
  "path": "/api/users",
  "method": "GET"
}
```

### Error Response

```json
{
  "error": "No servers available",
  "timestamp": "14:30:15"
}
```

## Docker

The application runs in Docker with:
- **OpenSwoole** - High-performance PHP server
- **Hot reload** - File watching with automatic restart
- **Volume mounts** - Live code editing
- **Composer** - Dependency management

### Container Services

- `swoole` - Main application server
- `test` - Testing environment (run with `--profile test`)

## Requirements

- Docker & Docker Compose
- PHP 8.4+ (in container)
- OpenSwoole extension (in container)

## License

MIT License