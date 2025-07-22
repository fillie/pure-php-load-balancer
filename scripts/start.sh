#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")"/.. && pwd)"
COMPOSE_FILE="$ROOT_DIR/docker/docker-compose.yml"
CONTAINER_NAME="docker-swoole-1"
VERBOSE=false
NO_CACHE=false

# Parse arguments
while [[ $# -gt 0 ]]; do
  case $1 in
    -v|--verbose)
      VERBOSE=true
      shift
      ;;
    --no-cache)
      NO_CACHE=true
      shift
      ;;
    *)
      echo "Usage: $0 [-v|--verbose] [--no-cache]"
      exit 1
      ;;
  esac
done

if [ "$VERBOSE" = true ]; then
    echo "Building and starting OpenSwoole dev stack..."
    if [ "$NO_CACHE" = true ]; then
        echo "Stopping and removing existing containers..."
        docker compose -f "$COMPOSE_FILE" down
        echo "Building with --no-cache flag..."
        docker compose -f "$COMPOSE_FILE" build --no-cache
        docker compose -f "$COMPOSE_FILE" up -d
    else
        docker compose -f "$COMPOSE_FILE" up --build -d
    fi
    
    echo "Waiting for container to be ready..."
    sleep 3
    
    echo "Syncing vendor directory for IDE support..."
    docker cp "${CONTAINER_NAME}:/app/vendor" "${ROOT_DIR}/" 2>/dev/null || echo "Could not sync vendor directory"
    
    echo "Attaching to container logs..."
    docker compose -f "$COMPOSE_FILE" logs -f
else
    if [ "$NO_CACHE" = true ]; then
        docker compose -f "$COMPOSE_FILE" down >/dev/null 2>&1
        docker compose -f "$COMPOSE_FILE" build --no-cache >/dev/null 2>&1
        docker compose -f "$COMPOSE_FILE" up -d >/dev/null 2>&1
    else
        docker compose -f "$COMPOSE_FILE" up --build -d >/dev/null 2>&1
    fi
    sleep 3
    docker cp "${CONTAINER_NAME}:/app/vendor" "${ROOT_DIR}/" >/dev/null 2>&1
    echo "🚀 Load balancer ready at http://localhost:9501"
    docker compose -f "$COMPOSE_FILE" logs -f
fi
