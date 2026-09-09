#!/usr/bin/env bash

set -euo pipefail

[[ "${OPS03_REHEARSAL:-}" == true ]] || { printf 'OPS-03 destruction requires OPS03_REHEARSAL=true\n' >&2; exit 1; }
[[ "${OPS03_ALLOW_DESTRUCTIVE:-}" == YES_I_UNDERSTAND ]] || { printf 'explicit destructive acknowledgement required\n' >&2; exit 1; }
[[ "${COMPOSE_PROJECT_NAME:-}" == ead2026-ops03 ]] || { printf 'destruction requires the canonical disposable Compose project\n' >&2; exit 1; }
[[ "${PRODUCTION_DB_VOLUME:-}" == ead2026-ops03-db \
    && "${PRODUCTION_STORAGE_VOLUME:-}" == ead2026-ops03-storage \
    && "${PRODUCTION_CACHE_VOLUME:-}" == ead2026-ops03-cache ]] || {
    printf 'destruction requires canonical disposable volume names\n' >&2
    exit 1
}

for volume_identity in \
    'ead2026-ops03-db|production_db_data' \
    'ead2026-ops03-storage|production_storage' \
    'ead2026-ops03-cache|production_cache'; do
    volume_name="${volume_identity%%|*}"
    compose_volume="${volume_identity##*|}"
    inspected="$(docker volume inspect --format '{{.Name}}|{{index .Labels "com.docker.compose.project"}}|{{index .Labels "com.docker.compose.volume"}}' "$volume_name" 2>/dev/null || true)"
    [[ "$inspected" == "$volume_name|ead2026-ops03|$compose_volume" ]] || {
        printf 'destruction volume identity is unsafe: %s\n' "$volume_name" >&2
        exit 1
    }
done

compose_file="${COMPOSE_FILE:-compose.production.yaml}"
compose_env_args=()
if [[ -n "${OPS03_ENV_FILE:-}" ]]; then
    compose_env_args+=(--env-file "$OPS03_ENV_FILE")
fi
docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" down --remove-orphans
docker volume rm "$PRODUCTION_DB_VOLUME" "$PRODUCTION_STORAGE_VOLUME" "$PRODUCTION_CACHE_VOLUME"

printf 'destruction=PASS\n'
printf 'destroyed=db,storage,cache\n'
