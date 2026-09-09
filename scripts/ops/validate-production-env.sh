#!/usr/bin/env bash

set -euo pipefail

env_file="${1:-.env.production}"

if [[ ! -f "$env_file" ]]; then
    printf 'production env file not found: %s\n' "$env_file" >&2
    exit 1
fi

value() {
    local key="$1"
    sed -n "s/^${key}=//p" "$env_file" | head -n 1 | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

require_nonempty() {
    local key="$1"
    local current
    current="$(value "$key")"
    [[ -n "$current" ]] || { printf 'missing required key: %s\n' "$key" >&2; exit 1; }
}

for key in APP_ENV APP_KEY APP_BUILD_SHA APP_DOMAIN APP_URL TRUSTED_PROXIES TRUSTED_HOSTS \
    DB_DATABASE DB_ADMIN_PASSWORD DB_BOOTSTRAP_USERNAME DB_BOOTSTRAP_PASSWORD \
    DB_RUNTIME_USERNAME DB_RUNTIME_PASSWORD \
    DB_MIGRATION_USERNAME DB_MIGRATION_PASSWORD FILESYSTEM_DISK MEDIA_DISK \
    MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_FROM_ADDRESS CORS_ALLOWED_ORIGINS CADDY_TLS_DIRECTIVE; do
    require_nonempty "$key"
done

[[ "$(value APP_ENV)" == production ]] || { printf 'APP_ENV must be production\n' >&2; exit 1; }
[[ "$(value APP_DEBUG)" == false ]] || { printf 'APP_DEBUG must be false\n' >&2; exit 1; }
[[ "$(value APP_URL)" == https://* ]] || { printf 'APP_URL must use https\n' >&2; exit 1; }
[[ "$(value FILESYSTEM_DISK)" == local || "$(value FILESYSTEM_DISK)" == s3 ]] || { printf 'FILESYSTEM_DISK must be local or s3\n' >&2; exit 1; }
[[ "$(value MEDIA_DISK)" == local || "$(value MEDIA_DISK)" == s3 ]] || { printf 'MEDIA_DISK must be local or s3\n' >&2; exit 1; }
[[ "$(value SESSION_SECURE_COOKIE)" == true ]] || { printf 'SESSION_SECURE_COOKIE must be true\n' >&2; exit 1; }
[[ "$(value DB_RUNTIME_USERNAME)" != root ]] || { printf 'runtime DB user cannot be root\n' >&2; exit 1; }
[[ "$(value DB_RUNTIME_USERNAME)" != "$(value DB_MIGRATION_USERNAME)" ]] || { printf 'runtime and migration DB users must differ\n' >&2; exit 1; }
[[ "$(value DB_RUNTIME_USERNAME)" != "$(value DB_BOOTSTRAP_USERNAME)" ]] || { printf 'runtime and bootstrap DB users must differ\n' >&2; exit 1; }
[[ "$(value DB_DATABASE)" != testing && "$(value DB_DATABASE)" != *e2e* ]] || { printf 'production DB name cannot be testing/e2e\n' >&2; exit 1; }
[[ "$(value CORS_ALLOWED_ORIGINS)" != *\** ]] || { printf 'CORS origin wildcard is forbidden\n' >&2; exit 1; }
[[ "$(value CADDY_TLS_DIRECTIVE)" != internal ]] || { printf 'Caddy internal TLS is rehearsal-only\n' >&2; exit 1; }

if grep -Eq '^[A-Z][A-Z0-9_]*=.*REPLACE_WITH_' "$env_file"; then
    printf 'placeholder remains in production env\n' >&2
    exit 1
fi

printf 'production environment contract: PASS (%s)\n' "$env_file"
