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

reject_weak_secret() {
    local key="$1"
    local current
    current="$(value "$key")"
    case "${current,,}" in
        password|secret|changeme|change-me|default|dev|development|test|testing|e2e|root)
            printf '%s contains a development/default secret\n' "$key" >&2
            exit 1
            ;;
    esac
}

for key in APP_ENV APP_KEY APP_BUILD_SHA MIGRATION_MANIFEST_SHA APP_DOMAIN APP_URL TRUSTED_PROXIES TRUSTED_HOSTS \
    DB_DATABASE DB_ADMIN_PASSWORD DB_BOOTSTRAP_USERNAME DB_BOOTSTRAP_PASSWORD \
    DB_RUNTIME_USERNAME DB_RUNTIME_PASSWORD \
    DB_MIGRATION_USERNAME DB_MIGRATION_PASSWORD FILESYSTEM_DISK MEDIA_DISK \
    MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_FROM_ADDRESS CORS_ALLOWED_ORIGINS CADDY_TLS_DIRECTIVE \
    OPS04_ALERT_OWNER OPS04_ALERT_CHANNEL OPS04_REMOTE_BACKUP_DESTINATION \
    OPS04_REMOTE_BACKUP_CREDENTIAL OPS04_EXPECTED_RC_SHA OPS04_EXPECTED_COMPOSE_PROJECT \
    OPS04_EXPECTED_DB_DATABASE OPS04_EXPECTED_STORAGE_VOLUME OPS04_BACKUP_MANIFEST_KEY \
    OPS04_REMOTE_BACKUP_ADAPTER OPS04_REMOTE_BACKUP_VERIFY_ADAPTER; do
    require_nonempty "$key"
done

case "$(value APP_ENV)" in
    production) ;;
    rehearsal) [[ "$(value CADDY_TLS_DIRECTIVE)" == internal ]] || { printf 'rehearsal Caddy TLS must be internal\n' >&2; exit 1; } ;;
    *) printf 'APP_ENV must be production or rehearsal\n' >&2; exit 1 ;;
esac
[[ "$(value APP_DEBUG)" == false ]] || { printf 'APP_DEBUG must be false\n' >&2; exit 1; }
[[ "$(value APP_URL)" == https://* ]] || { printf 'APP_URL must use https\n' >&2; exit 1; }
[[ "$(value APP_BUILD_SHA)" =~ ^[a-f0-9]{40}$ ]] || { printf 'APP_BUILD_SHA must be the full release SHA\n' >&2; exit 1; }
[[ "$(value MIGRATION_MANIFEST_SHA)" != *REPLACE_WITH_* && "$(value MIGRATION_MANIFEST_SHA)" =~ ^[a-f0-9]{64}$ ]] || { printf 'migration manifest SHA is not provisioned\n' >&2; exit 1; }
app_key="$(value APP_KEY)"
[[ "$app_key" == base64:* ]] || { printf 'APP_KEY must be a base64 Laravel key\n' >&2; exit 1; }
app_key_payload="${app_key#base64:}"
app_key_bytes="$(printf '%s' "$app_key_payload" | base64 --decode 2>/dev/null | wc -c | tr -d ' ')"
[[ "$app_key_bytes" == 32 ]] || { printf 'APP_KEY must decode to 32 bytes\n' >&2; exit 1; }
[[ "$(value FILESYSTEM_DISK)" == local || "$(value FILESYSTEM_DISK)" == s3 ]] || { printf 'FILESYSTEM_DISK must be local or s3\n' >&2; exit 1; }
[[ "$(value MEDIA_DISK)" == local || "$(value MEDIA_DISK)" == s3 ]] || { printf 'MEDIA_DISK must be local or s3\n' >&2; exit 1; }
[[ "$(value SESSION_SECURE_COOKIE)" == true ]] || { printf 'SESSION_SECURE_COOKIE must be true\n' >&2; exit 1; }
[[ "$(value DB_RUNTIME_USERNAME)" != root ]] || { printf 'runtime DB user cannot be root\n' >&2; exit 1; }
[[ "$(value DB_RUNTIME_USERNAME)" != "$(value DB_MIGRATION_USERNAME)" ]] || { printf 'runtime and migration DB users must differ\n' >&2; exit 1; }
[[ "$(value DB_RUNTIME_USERNAME)" != "$(value DB_BOOTSTRAP_USERNAME)" ]] || { printf 'runtime and bootstrap DB users must differ\n' >&2; exit 1; }
[[ "$(value DB_DATABASE)" != testing && "$(value DB_DATABASE)" != *e2e* ]] || { printf 'production DB name cannot be testing/e2e\n' >&2; exit 1; }
[[ "$(value PRODUCTION_DB_VOLUME)" != *REPLACE_WITH_* && "$(value PRODUCTION_STORAGE_VOLUME)" != *REPLACE_WITH_* && "$(value PRODUCTION_CACHE_VOLUME)" != *REPLACE_WITH_* ]] || { printf 'production volume names must be provisioned\n' >&2; exit 1; }
[[ "$(value CORS_ALLOWED_ORIGINS)" != *\** ]] || { printf 'CORS origin wildcard is forbidden\n' >&2; exit 1; }
[[ "$(value OPS04_ALERT_OWNER)" != *REPLACE_WITH_* ]] || { printf 'alert owner placeholder remains\n' >&2; exit 1; }
[[ "$(value OPS04_REMOTE_BACKUP_DESTINATION)" != *REPLACE_WITH_* ]] || { printf 'remote backup destination placeholder remains\n' >&2; exit 1; }
[[ "$(value OPS04_REMOTE_BACKUP_CREDENTIAL)" != *REPLACE_WITH_* ]] || { printf 'remote backup credential placeholder remains\n' >&2; exit 1; }
[[ "$(value OPS04_EXPECTED_RC_SHA)" != *REPLACE_WITH_* && "$(value OPS04_EXPECTED_RC_SHA)" =~ ^[0-9a-f]{7,40}$ ]] || { printf 'expected release SHA is not provisioned\n' >&2; exit 1; }
[[ "$(value OPS04_EXPECTED_COMPOSE_PROJECT)" =~ ^[A-Za-z0-9._-]+$ ]] || { printf 'expected Compose project is invalid\n' >&2; exit 1; }
[[ "$(value OPS04_EXPECTED_DB_DATABASE)" =~ ^[A-Za-z0-9_]+$ ]] || { printf 'expected database identity is invalid\n' >&2; exit 1; }
[[ "$(value OPS04_EXPECTED_STORAGE_VOLUME)" =~ ^[A-Za-z0-9._-]+$ ]] || { printf 'expected storage volume identity is invalid\n' >&2; exit 1; }
[[ "$(value OPS04_BACKUP_MANIFEST_KEY)" != *REPLACE_WITH_* ]] || { printf 'backup manifest signing key placeholder remains\n' >&2; exit 1; }
[[ "$(value OPS04_REMOTE_BACKUP_ADAPTER)" != *REPLACE_WITH_* && "$(value OPS04_REMOTE_BACKUP_VERIFY_ADAPTER)" != *REPLACE_WITH_* ]] || { printf 'remote backup adapters are not provisioned\n' >&2; exit 1; }
alert_channel="$(value OPS04_ALERT_CHANNEL)"
[[ "$alert_channel" =~ ^[A-Za-z0-9._:/\ -]+$ ]] || { printf 'alert channel contains unsafe characters\n' >&2; exit 1; }
for secret_key in DB_ADMIN_PASSWORD DB_BOOTSTRAP_PASSWORD DB_RUNTIME_PASSWORD DB_MIGRATION_PASSWORD OPS04_REMOTE_BACKUP_CREDENTIAL; do
    reject_weak_secret "$secret_key"
done
[[ "$(value MAIL_MAILER)" != smtp || "$(value MAIL_PASSWORD)" != *REPLACE_WITH_* ]] || { printf 'SMTP password placeholder remains\n' >&2; exit 1; }
if [[ -n "$(value OPS04_ALERT_WEBHOOK_URL)" ]]; then
    [[ "$(value OPS04_ALERT_WEBHOOK_URL)" == https://* ]] || { printf 'alert webhook must use https\n' >&2; exit 1; }
    [[ "$(value OPS04_ALERT_WEBHOOK_URL)" != *REPLACE_WITH_* ]] || { printf 'alert webhook placeholder remains\n' >&2; exit 1; }
fi
if [[ -n "$(value OPS04_ALERT_EMAIL_TO)" ]]; then
    [[ "$(value OPS04_ALERT_EMAIL_TO)" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$ ]] || { printf 'alert email recipient is invalid\n' >&2; exit 1; }
    [[ "$(value OPS04_ALERT_EMAIL_FROM)" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$ ]] || { printf 'alert email sender is invalid\n' >&2; exit 1; }
fi
if [[ "$(value APP_ENV)" == production ]]; then
    [[ "$(value CADDY_TLS_DIRECTIVE)" != internal ]] || { printf 'Caddy internal TLS is rehearsal-only\n' >&2; exit 1; }
fi

if grep -Eq '^[A-Z][A-Z0-9_]*=.*REPLACE_WITH_' "$env_file"; then
    printf 'placeholder remains in production env\n' >&2
    exit 1
fi

printf 'production environment contract: PASS (%s)\n' "$env_file"
