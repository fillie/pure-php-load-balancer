#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")"/.. && pwd)"
COMPOSE_FILE="$ROOT_DIR/docker/docker-compose.yml"

# Default to check mode
FIX_MODE=false

# Parse arguments
for arg in "$@"; do
    case $arg in
        --fix)
            FIX_MODE=true
            shift
            ;;
        *)
            echo "Usage: $0 [--fix]"
            echo "  --fix    Fix code style issues (default: check only)"
            exit 1
            ;;
    esac
done

if [ "$FIX_MODE" = true ]; then
    echo "🔧 Fixing code style..."
    docker compose -f "$COMPOSE_FILE" exec swoole ./vendor/bin/php-cs-fixer fix
else
    echo "🔍 Checking code style..."
    docker compose -f "$COMPOSE_FILE" exec swoole ./vendor/bin/php-cs-fixer fix --dry-run
fi