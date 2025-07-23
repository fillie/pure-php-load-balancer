#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")"/.. && pwd)"
COMPOSE_FILE="$ROOT_DIR/docker/docker-compose.yml"

echo "🔍 Checking code style..."
docker compose -f "$COMPOSE_FILE" exec swoole ./vendor/bin/php-cs-fixer fix --dry-run