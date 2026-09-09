#!/usr/bin/env bash

set -euo pipefail

[[ "${OPS03_REHEARSAL:-}" == true ]] || { printf 'OPS-03 readiness requires OPS03_REHEARSAL=true\n' >&2; exit 1; }
[[ "${COMPOSE_PROJECT_NAME:-}" == *ops03* ]] || { printf 'OPS-03 readiness requires an ops03 Compose project\n' >&2; exit 1; }

compose_file="${COMPOSE_FILE:-compose.production.yaml}"
expected_migrations="${OPS03_EXPECTED_MIGRATIONS:-73}"

docker compose -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" ps --status running --services | grep -qx db
docker compose -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" ps --status running --services | grep -qx app
docker compose -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" ps --status running --services | grep -qx scheduler
docker compose -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" exec -T app test -d /var/www/html/storage/app/private
docker compose -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" exec -T app sh -c 'test -w /var/www/html/storage/app/private'

migrations="$(docker compose -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" exec -T app php artisan migrate:status --no-ansi | awk '$2 == "Ran" {count++} END {print count+0}')"
[[ "$migrations" == "$expected_migrations" ]] || {
    printf 'readiness migration count mismatch: expected %s, got %s\n' "$expected_migrations" "$migrations" >&2
    exit 1
}

printf 'readiness=PASS\n'
printf 'migrations=%s\n' "$migrations"
printf 'scheduler=required-and-running\n'
printf 'storage=writable\n'
