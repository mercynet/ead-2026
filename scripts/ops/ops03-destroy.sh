#!/usr/bin/env bash

set -euo pipefail

[[ "${OPS03_REHEARSAL:-}" == true ]] || { printf 'OPS-03 destruction requires OPS03_REHEARSAL=true\n' >&2; exit 1; }
[[ "${OPS03_ALLOW_DESTRUCTIVE:-}" == YES_I_UNDERSTAND ]] || { printf 'explicit destructive acknowledgement required\n' >&2; exit 1; }
[[ "${COMPOSE_PROJECT_NAME:-}" == *ops03* ]] || { printf 'destruction requires an ops03 Compose project\n' >&2; exit 1; }
[[ "${PRODUCTION_DB_VOLUME:-}" == *ops03* && "${PRODUCTION_STORAGE_VOLUME:-}" == *ops03* ]] || {
    printf 'destruction requires ops03-only volume names\n' >&2
    exit 1
}

compose_file="${COMPOSE_FILE:-compose.production.yaml}"
compose_env_args=()
if [[ -n "${OPS03_ENV_FILE:-}" ]]; then
    compose_env_args+=(--env-file "$OPS03_ENV_FILE")
fi
docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" down --remove-orphans
docker volume rm "$PRODUCTION_DB_VOLUME" "$PRODUCTION_STORAGE_VOLUME" "$PRODUCTION_CACHE_VOLUME"

printf 'destruction=PASS\n'
printf 'destroyed=db,storage,cache\n'
