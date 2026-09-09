#!/usr/bin/env bash

set -euo pipefail

[[ "${OPS03_REHEARSAL:-}" == true ]] || { printf 'OPS-03 readiness requires OPS03_REHEARSAL=true\n' >&2; exit 1; }
[[ "${COMPOSE_PROJECT_NAME:-}" == ead2026-ops03 ]] || { printf 'OPS-03 readiness requires the canonical disposable Compose project\n' >&2; exit 1; }
[[ "${PRODUCTION_STORAGE_VOLUME:-}" == ead2026-ops03-storage ]] || { printf 'OPS-03 readiness requires the canonical disposable storage volume\n' >&2; exit 1; }

compose_file="${COMPOSE_FILE:-compose.production.yaml}"
compose_env_args=()
if [[ -n "${OPS03_ENV_FILE:-}" ]]; then
    compose_env_args+=(--env-file "$OPS03_ENV_FILE")
fi
volume_identity="$(docker volume inspect --format '{{.Name}}|{{index .Labels "com.docker.compose.project"}}|{{index .Labels "com.docker.compose.volume"}}' "$PRODUCTION_STORAGE_VOLUME" 2>/dev/null)"
[[ "$volume_identity" == "ead2026-ops03-storage|ead2026-ops03|production_storage" ]] || {
    printf 'OPS-03 readiness storage volume identity is unsafe\n' >&2
    exit 1
}

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
