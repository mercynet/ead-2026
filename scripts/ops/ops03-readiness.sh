#!/usr/bin/env bash

set -euo pipefail

[[ "${OPS03_REHEARSAL:-}" == true ]] || { printf 'OPS-03 readiness requires OPS03_REHEARSAL=true\n' >&2; exit 1; }
[[ "${COMPOSE_PROJECT_NAME:-}" == *ops03* ]] || { printf 'OPS-03 readiness requires an ops03 Compose project\n' >&2; exit 1; }
[[ -n "${PRODUCTION_STORAGE_VOLUME:-}" ]] || { printf 'OPS-03 readiness requires the storage volume name\n' >&2; exit 1; }

compose_file="${COMPOSE_FILE:-compose.production.yaml}"
compose_env_args=()
if [[ -n "${OPS03_ENV_FILE:-}" ]]; then
    compose_env_args+=(--env-file "$OPS03_ENV_FILE")
fi
docker volume inspect "$PRODUCTION_STORAGE_VOLUME" >/dev/null

docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" ps --status running --services | grep -qx db
docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" ps --status running --services | grep -qx app
docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" ps --status running --services | grep -qx scheduler
docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" exec -T app test -d /var/www/html/storage/app/private
docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" exec -T app sh -c 'test -w /var/www/html/storage/app/private'

migration_output="$(docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" exec -T app php artisan ops:migrate --check-only --no-interaction)"
printf '%s\n' "$migration_output"
migrations="$(printf '%s\n' "$migration_output" | sed -n 's/^applied_after=//p')"
[[ -n "$migrations" ]] || {
    printf 'readiness migration manifest did not report applied_after\n' >&2
    exit 1
}

printf 'readiness=PASS\n'
printf 'migrations=%s\n' "$migrations"
printf 'scheduler=required-and-running\n'
printf 'storage=writable\n'
