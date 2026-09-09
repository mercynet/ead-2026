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
docker compose -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" up -d db app scheduler web
docker compose -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" exec -T app php artisan optimize:clear
docker compose -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" run --rm \
    -e DB_USERNAME="$DB_MIGRATION_USERNAME" \
    -e DB_PASSWORD="$DB_MIGRATION_PASSWORD" \
    app php artisan migrate --force --no-interaction

printf 'deploy=PASS\n'
printf 'sha=%s\n' "$APP_BUILD_SHA"
