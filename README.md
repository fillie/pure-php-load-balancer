# PHP Load Balancer

A high-performance PHP load balancer built with OpenSwoole and PHP-DI, featuring a clean architecture with comprehensive testing and modern PHP practices.

## Features

- **Round-robin load balancing** - Distributes requests evenly across backend servers
- **High-performance HTTP server** - Built on OpenSwoole for exceptional performance
- **Clean architecture** - Dependency injection, interfaces, and separation of concerns
- **Type safety** - Full PHP 8.4+ type declarations with readonly properties
- **PSR-3 logging** - Structured logging with configurable levels
- **Hot reload in development** - Automatic code reloading when files change
- **Environment-based configuration** - Type-safe configuration management
- **Comprehensive testing** - 118 tests covering all components
- **Docker support** - Containerized development environment
- **Graceful shutdown** - Proper signal handling (SIGTERM, SIGINT, SIGHUP)

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

3. **Run tests:**
   ```bash
   ./scripts/test.sh
   ```

## Scripts

- `./scripts/start.sh` - Start the load balancer (with cache)
- `./scripts/start.sh --no-cache` - Start with fresh build (for new dependencies)
- `./scripts/start.sh -v` - Start with verbose output
- `./scripts/test.sh` - Run tests
- `./scripts/test.sh -v` - Run tests with verbose output

## Configuration

Configuration is managed via a type-safe `Config` class that reads from environment variables:

### Environment Variables

- `APP_ENV` - Application environment (`development`, `production`, `testing`)
- `SERVER_HOST` - Server host (default: `0.0.0.0`)
- `SERVER_PORT` - Server port (default: `9501`)
- `ENABLE_OUTPUT` - Enable request logging (`true`, `false`)
- `LOG_LEVEL` - Logging level (`debug`, `info`, `warning`, `error`)
- `DEFAULT_SERVERS` - Comma-separated list of backend servers

Example `.env` file:
```env
APP_ENV=development
SERVER_HOST=0.0.0.0
SERVER_PORT=9501
ENABLE_OUTPUT=true
LOG_LEVEL=info
DEFAULT_SERVERS=http://localhost:8080,http://localhost:8081,http://localhost:8082
```

## Architecture

### Core Components

- **Configuration Layer** (`Infrastructure/Config`) - Type-safe configuration management with environment variable parsing
- **Domain Layer** (`Domain/LoadBalancer`) - Core business logic with `RoundRobinLoadBalancer` implementing pluggable algorithms
- **Application Layer** (`Application/Http`) - HTTP server, request handlers, and response DTOs using OpenSwoole
- **Support Layer** (`Support`) - Cross-cutting utilities like `ResponseBuilder` for standardized response creation
- **Infrastructure Layer** (`Infrastructure`) - External concerns like logging (`ConsoleLogger`) and system clock

### Architecture Highlights

- **Dependency Injection** - PHP-DI container with interface-based design
- **Immutability** - Readonly properties and value objects where appropriate
- **Type Safety** - Final classes, strict typing, and proper error handling
- **SOLID Principles** - Single responsibility, dependency inversion, interface segregation
- **Exception Handling** - Specific exceptions (`NoHealthyServersException`) with appropriate HTTP status codes

### Request Flow

1. OpenSwoole HTTP server receives request in `HttpServer::handleRequest()`
2. Request metadata extracted into `RequestMeta` value object (`Application/Http/Request`)
3. `RequestHandler` coordinates the request processing
4. `RoundRobinLoadBalancer` selects next server using round-robin algorithm (`Domain/LoadBalancer`)
5. `ResponseBuilder` creates standardized response DTOs (`Support`)
6. `JsonResponse` handles the HTTP response formatting (`Application/Http/Response`)
7. Structured logging with PSR-3 logger including request context (`Infrastructure/Logger`)

## API

### Success Response

```json
{
  "success": true,
  "message": "Load balancer is working",
  "timestamp": "2025-07-22T16:36:53+00:00",
  "target_server": "http://localhost:8080",
  "data": {
    "path": "/api/users",
    "method": "GET",
    "client_ip": "192.168.65.1"
  }
}
```

### Service Unavailable Response

```json
{
  "success": false,
  "error": "No healthy servers available",
  "timestamp": "2025-07-22T16:36:53+00:00"
}
```

### Internal Error Response

```json
{
  "success": false,
  "error": "Internal server error",
  "timestamp": "2025-07-22T16:36:53+00:00",
  "context": {
    "request": {
      "method": "GET",
      "path": "/",
      "client_ip": "127.0.0.1",
      "timestamp": "2025-07-22 16:36:53"
    }
  }
}
```

## Development

### Hot Reload

Hot reload is automatically enabled in development mode:
- Edit files in `src/` directory
- Changes are automatically detected and reloaded via watchexec
- No need to restart the container
- File watching uses debounce to prevent excessive reloads

### Adding Dependencies

When adding new Composer dependencies:
```bash
# Add to composer.json, then rebuild
./scripts/start.sh --no-cache
```

### Testing

The project has comprehensive test coverage:

```bash
./scripts/test.sh
```

**Test Coverage:**
- **118 tests** with 454+ assertions
- **Infrastructure tests** - Config, SystemClock, and ConsoleLogger with comprehensive edge cases
- **Application tests** - HTTP server, request handlers, and response DTOs
- **Domain tests** - Round-robin load balancer algorithm and server management
- **Support tests** - ResponseBuilder for standardized response creation
- Tests follow clean architecture with proper separation of concerns

Tests use a separate `.env.testing` configuration with structured output.

### Code Quality

- **PHP 8.4+** with strict types and modern features
- **Final classes** to prevent inheritance issues
- **Readonly properties** for immutability where appropriate
- **Promoted constructor properties** to reduce boilerplate
- **PSR-3 logging** with proper context and interpolation
- **Type-safe configuration** with proper defaults and validation

## Docker

The application runs in a multi-stage Docker setup:

### Features
- **OpenSwoole** - High-performance async PHP server
- **Hot reload** - File watching with automatic restart via watchexec
- **Volume mounts** - Live code editing during development
- **Composer caching** - Optimized dependency management
- **Multi-environment** - Separate configs for dev/test/prod

### Container Services

- `swoole` - Main application server (port 9501)
- `test` - Testing environment with shared vendor volume

### Performance Optimizations

- **Swoole async signals** - Proper signal handling without blocking
- **Optimized autoloader** - Composer autoloader optimization
- **Cached config lookups** - Configuration values cached at startup
- **Efficient JSON encoding** - Proper flags and error handling

## Requirements

- Docker & Docker Compose
- PHP 8.4+ (in container)
- OpenSwoole extension (in container)
- PCNTL extension (for signal handling)

## License

MIT License