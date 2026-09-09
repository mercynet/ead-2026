#!/usr/bin/env bash

set -euo pipefail

[[ "${OPS03_REHEARSAL:-}" == true ]] || { printf 'OPS-03 deploy requires OPS03_REHEARSAL=true\n' >&2; exit 1; }
[[ "${COMPOSE_PROJECT_NAME:-}" == *ops03* ]] || { printf 'OPS-03 deploy requires an ops03 Compose project\n' >&2; exit 1; }
[[ "${APP_BUILD_SHA:-}" =~ ^[0-9a-f]{7,40}$ ]] || { printf 'APP_BUILD_SHA must be a git SHA\n' >&2; exit 1; }
[[ -n "${OPS03_BACKUP_DIR:-}" && -f "${OPS03_BACKUP_DIR}/manifest.txt" ]] || {
    printf 'valid predeploy backup is required\n' >&2
    exit 1
}
grep -qx 'status=PASS' "$OPS03_BACKUP_DIR/manifest.txt" || { printf 'predeploy backup is not valid\n' >&2; exit 1; }

compose_file="${COMPOSE_FILE:-compose.production.yaml}"
compose_env_args=()
if [[ -n "${OPS03_ENV_FILE:-}" ]]; then
    compose_env_args+=(--env-file "$OPS03_ENV_FILE")
fi
docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" stop scheduler web >/dev/null 2>&1 || true
docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" up -d db app
docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" exec -T app php artisan optimize:clear

[[ -n "${OPS03_ENV_FILE:-}" && -f "$OPS03_ENV_FILE" ]] || {
    printf 'OPS03_ENV_FILE is required for migration credentials\n' >&2
    exit 1
}
migration_username="$(awk -F= '$1 == "DB_MIGRATION_USERNAME" { print substr($0, index($0, "=") + 1) }' "$OPS03_ENV_FILE")"
migration_password="$(awk -F= '$1 == "DB_MIGRATION_PASSWORD" { print substr($0, index($0, "=") + 1) }' "$OPS03_ENV_FILE")"

docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" run --rm \
    -e DB_MIGRATION_USERNAME="$migration_username" \
    -e DB_MIGRATION_PASSWORD="$migration_password" \
    app sh -c 'DB_USERNAME="$DB_MIGRATION_USERNAME" DB_PASSWORD="$DB_MIGRATION_PASSWORD" php artisan ops:migrate --force --no-interaction'

docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" up -d scheduler web

printf 'deploy=PASS\n'
printf 'sha=%s\n' "$APP_BUILD_SHA"
