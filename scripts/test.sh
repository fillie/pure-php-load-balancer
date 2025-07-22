#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")"/.. && pwd)"
COMPOSE_FILE="$ROOT_DIR/docker/docker-compose.yml"
VERBOSE=false

# Parse arguments
while [[ $# -gt 0 ]]; do
  case $1 in
    -v|--verbose)
      VERBOSE=true
      shift
      ;;
    *)
      echo "Usage: $0 [-v|--verbose]"
      exit 1
      ;;
  esac
done

if [ "$VERBOSE" = true ]; then
    echo "Running tests in fresh container..."
    docker compose -f "$COMPOSE_FILE" run --rm test
else
    echo "🧪 Running tests..."
    docker compose -f "$COMPOSE_FILE" run --rm test
fi