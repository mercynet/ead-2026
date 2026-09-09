#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "$script_dir/../.." && pwd)"
mode=dry-run
env_file="${PAID_PILOT_ENV_FILE:-.env.production}"
receipt_file="${PAID_PILOT_RECEIPT_FILE:-}"

usage() {
    cat <<'EOF'
Usage: activate-paid-pilot.sh [--dry-run] [--execute] [--env-file PATH] [--receipt PATH]

Dry-run is the default. --execute requires:
  PAID_PILOT_ALLOW_MUTATION=true
  PAID_PILOT_ACTIVATION_CONFIRM=I_UNDERSTAND

The command delegates to the existing production validation, backup, migration,
readiness, domain/TLS, monitoring and synthetic gates. It never prints env values.
EOF
}

while (($# > 0)); do
    case "$1" in
        --dry-run) mode=dry-run ;;
        --execute) mode=execute ;;
        --env-file)
            shift
            env_file="${1:?--env-file requires a path}"
            ;;
        --receipt)
            shift
            receipt_file="${1:?--receipt requires a path}"
            ;;
        --help|-h) usage; exit 0 ;;
        *) printf 'unknown argument: %s\n' "$1" >&2; usage >&2; exit 2 ;;
    esac
    shift
done

print_external_pending() {
    printf 'external_input=%s status=EXTERNAL_PENDING\n' "$1"
}

print_gate() {
    printf 'gate=%s command=%s\n' "$1" "$2"
}

run_gate() {
    local output_file="$1"
    local expected_pattern="$2"
    local gate_name="$3"
    shift 3

    if ! "$@" >"$output_file" 2>&1; then
        sed -n '1,240p' "$output_file" >&2
        printf 'gate=%s status=FAIL\n' "$gate_name" >&2
        exit 1
    fi
    if ! grep -Eq "$expected_pattern" "$output_file"; then
        sed -n '1,240p' "$output_file" >&2
        printf 'gate=%s status=FAIL reason=unexpected_output\n' "$gate_name" >&2
        exit 1
    fi
    sed -n '1,240p' "$output_file"
}

scribe_docs_hash() {
    local docs_dir="$1"

    find "$docs_dir" -type f ! -name '.release-identity' -print0 \
        | sort -z \
        | xargs -0r sha256sum \
        | sha256sum \
        | awk '{print $1}'
}

if [[ "$mode" == dry-run ]]; then
    printf 'paid_pilot_activation=DRY_RUN\n'
    printf 'mutation=none\n'
    if [[ -f "$env_file" ]]; then
        print_gate validate-production-env "$script_dir/validate-production-env.sh $env_file"
        if ! "$script_dir/validate-production-env.sh" "$env_file"; then
            printf 'dry_run_validation=FAIL\n' >&2
            exit 1
        fi
    else
        print_external_pending "env_file:$env_file"
    fi
    print_gate verify-rc "HEAD, clean checkout, image labels and digests bound to APP_BUILD_SHA"
    print_gate destructive-guards "explicit PAID_PILOT_ALLOW_MUTATION and PAID_PILOT_ACTIVATION_CONFIRM"
    print_gate backup-prerequisite "$script_dir/ops04-backup-monitor.sh"
    print_gate remote-backup "$script_dir/ops04-remote-backup.sh <latest-local-backup>"
    print_gate alert-delivery "$script_dir/ops04-alert-canary.sh"
    print_gate scribe "composer docs + content hash bound to APP_BUILD_SHA"
    print_gate migration "docker compose --env-file <env> -f <compose> -p <project> run --rm app php artisan ops:migrate --force --no-interaction"
    print_gate services "docker compose ... up -d db app scheduler web"
    print_gate readiness "$script_dir/ops04-readiness.sh"
    print_gate monitoring "$script_dir/ops04-deploy-observe.sh"
    print_gate domain-tls "$script_dir/ops04-domain-tls.sh live"
    print_gate synthetic "$script_dir/ops04-synthetic.sh"
    print_external_pending "host/domain/tls/secrets/db-users/remote-backup/owners/scheduler-human-acceptance"
    printf 'receipt=not_written\n'
    exit 0
fi

[[ -f "$env_file" ]] || { printf 'env file not found: %s\n' "$env_file" >&2; exit 1; }
[[ "${PAID_PILOT_ALLOW_MUTATION:-}" == true ]] || {
    printf 'execution requires PAID_PILOT_ALLOW_MUTATION=true\n' >&2
    exit 1
}
[[ "${PAID_PILOT_ACTIVATION_CONFIRM:-}" == I_UNDERSTAND ]] || {
    printf 'execution requires PAID_PILOT_ACTIVATION_CONFIRM=I_UNDERSTAND\n' >&2
    exit 1
}

"$script_dir/validate-production-env.sh" "$env_file"

set +u
set -a
source "$env_file"
set +a
set -u

[[ "${APP_ENV:-}" == production || "${APP_ENV:-}" == rehearsal ]] || {
    printf 'APP_ENV must be production or rehearsal\n' >&2
    exit 1
}
[[ "${DB_DATABASE:-}" != testing && "${DB_DATABASE:-}" != *e2e* ]] || {
    printf 'activation refuses testing/e2e database\n' >&2
    exit 1
}
[[ "${APP_BUILD_SHA:-}" =~ ^[0-9a-f]{7,40}$ ]] || {
    printf 'APP_BUILD_SHA must be a git SHA\n' >&2
    exit 1
}
[[ "${APP_BUILD_SHA}" =~ ^[0-9a-f]{40}$ ]] || {
    printf 'APP_BUILD_SHA must be the full 40-character release SHA\n' >&2
    exit 1
}
[[ "${APP_DOMAIN:-}" =~ ^[A-Za-z0-9.-]+$ ]] || {
    printf 'APP_DOMAIN must be a safe hostname for the receipt\n' >&2
    exit 1
}

if git -C "$repo_root" rev-parse --git-dir >/dev/null 2>&1; then
    release_sha="$(git -C "$repo_root" rev-parse "${APP_BUILD_SHA}^{commit}" 2>/dev/null || true)"
    head_sha="$(git -C "$repo_root" rev-parse HEAD)"
    [[ "$release_sha" == "$head_sha" ]] || {
        printf 'release SHA must equal the checked-out HEAD\n' >&2
        exit 1
    }
    [[ -z "$(git -C "$repo_root" status --porcelain --untracked-files=all)" ]] || {
        printf 'release checkout must be clean before activation\n' >&2
        exit 1
    }
    [[ -n "$release_sha" ]] || {
        printf 'release SHA is not present in the checkout\n' >&2
        exit 1
    }
else
    printf 'release checkout provenance is unavailable\n' >&2
    exit 1
fi

compose_file="${COMPOSE_FILE:-compose.production.yaml}"
compose_project="${COMPOSE_PROJECT_NAME:-ead2026-production}"
compose_env=(--env-file "$env_file")
compose=(docker compose "${compose_env[@]}" -f "$compose_file" -p "$compose_project")
temp_dir="$(mktemp -d)"
trap 'rm -rf "$temp_dir"' EXIT

[[ "${MIGRATION_MANIFEST_SHA:-}" =~ ^[a-f0-9]{64}$ ]] || {
    printf 'MIGRATION_MANIFEST_SHA must be a SHA-256 release identity\n' >&2
    exit 1
}
release_manifest="$repo_root/release/migrations.manifest.json"
[[ -s "$release_manifest" ]] || {
    printf 'release migration manifest is missing from the checkout\n' >&2
    exit 1
}
[[ "$(sha256sum "$release_manifest" | awk '{print $1}')" == "$MIGRATION_MANIFEST_SHA" ]] || {
    printf 'release migration manifest hash does not match the env identity\n' >&2
    exit 1
}
[[ "${OPS04_EXPECTED_RC_SHA:-}" == "$APP_BUILD_SHA" ]] || {
    printf 'backup monitor expected RC SHA must match APP_BUILD_SHA\n' >&2
    exit 1
}
[[ "${OPS04_EXPECTED_COMPOSE_PROJECT:-}" == "$compose_project" ]] || {
    printf 'backup monitor expected Compose project must match activation\n' >&2
    exit 1
}
[[ "${OPS04_EXPECTED_DB_DATABASE:-}" == "${DB_DATABASE}" ]] || {
    printf 'backup monitor expected database must match activation\n' >&2
    exit 1
}
[[ "${OPS04_EXPECTED_STORAGE_VOLUME:-}" == "${PRODUCTION_STORAGE_VOLUME}" ]] || {
    printf 'backup monitor expected storage volume must match activation\n' >&2
    exit 1
}

"${compose[@]}" config --quiet

app_image="${APP_IMAGE:-ead2026/app}:${APP_BUILD_SHA}"
web_image="${WEB_IMAGE:-ead2026/web}:${APP_BUILD_SHA}"
app_identity="$(docker image inspect "$app_image" --format '{{.Id}}|{{index .Config.Labels "org.opencontainers.image.revision"}}|{{index .Config.Labels "org.opencontainers.image.migrations.manifest.sha256"}}')"
web_identity="$(docker image inspect "$web_image" --format '{{.Id}}|{{index .Config.Labels "org.opencontainers.image.revision"}}|{{index .Config.Labels "org.opencontainers.image.migrations.manifest.sha256"}}')"
IFS='|' read -r app_digest app_revision app_manifest_sha <<<"$app_identity"
IFS='|' read -r web_digest web_revision web_manifest_sha <<<"$web_identity"
[[ "$app_digest" =~ ^sha256:[a-f0-9]{64}$ && "$web_digest" =~ ^sha256:[a-f0-9]{64}$ ]] || {
    printf 'release images do not expose immutable digests\n' >&2
    exit 1
}
[[ "$app_revision" == "$APP_BUILD_SHA" && "$web_revision" == "$APP_BUILD_SHA" ]] || {
    printf 'release image revision labels do not match APP_BUILD_SHA\n' >&2
    exit 1
}
[[ "$app_manifest_sha" == "$MIGRATION_MANIFEST_SHA" && "$web_manifest_sha" == "$MIGRATION_MANIFEST_SHA" ]] || {
    printf 'release image migration manifest labels do not match the release manifest\n' >&2
    exit 1
}

scribe_docs_dir="${PAID_PILOT_SCRIBE_DOCS_DIR:-$repo_root/public/docs}"
if [[ -x "$repo_root/vendor/bin/sail" ]]; then
    (cd "$repo_root" && ./vendor/bin/sail composer docs)
elif command -v composer >/dev/null 2>&1; then
    (cd "$repo_root" && composer docs)
else
    printf 'Scribe generator is not available in the release checkout\n' >&2
    exit 1
fi
[[ -s "$scribe_docs_dir/collection.json" ]] || {
    printf 'Scribe collection was not generated\n' >&2
    exit 1
}
scribe_hash="$(scribe_docs_hash "$scribe_docs_dir")"
printf '%s\n' "$APP_BUILD_SHA" > "$scribe_docs_dir/.release-identity"
[[ -z "$(git -C "$repo_root" status --porcelain --untracked-files=all)" ]] || {
    printf 'Scribe generation changed tracked release files\n' >&2
    exit 1
}

support_owner="${PAID_PILOT_SUPPORT_OWNER_ID:-}"
human_approval="${PAID_PILOT_HUMAN_APPROVAL_REF:-}"
restore_reference="${PAID_PILOT_RESTORE_REFERENCE:-}"
[[ -n "$support_owner" && -n "$human_approval" && -n "$restore_reference" && "${PAID_PILOT_RPO_RTO_ACCEPTED:-}" == yes ]] || {
    printf 'support owner, restore reference, RPO/RTO acceptance and human approval reference are required before mutation\n' >&2
    exit 1
}
[[ "$support_owner" =~ ^[A-Za-z0-9._:@/-]+$ && "$human_approval" =~ ^[A-Za-z0-9._:@/-]+$ && "$restore_reference" =~ ^[A-Za-z0-9._:@/-]+$ ]] || {
    printf 'owner, restore and approval identifiers must use safe identifier characters\n' >&2
    exit 1
}

backup_root="${OPS04_BACKUP_ROOT:-}"
[[ -n "$backup_root" && -d "$backup_root" ]] || {
    printf 'OPS04_BACKUP_ROOT must point to a local backup directory\n' >&2
    exit 1
}
export OPS04_BACKUP_ROOT="$backup_root"
run_gate "$temp_dir/backup-monitor.txt" '^backup_monitor=PASS ' backup-monitor "$script_dir/ops04-backup-monitor.sh"
latest_manifest="$(find "$backup_root" -type f -name manifest.txt -print0 | xargs -0r ls -1t | head -n 1)"
[[ -n "$latest_manifest" ]] || { printf 'latest backup manifest not found\n' >&2; exit 1; }
latest_backup_dir="$(dirname "$latest_manifest")"
run_gate "$temp_dir/remote-backup.txt" '^remote_backup=PASS ' remote-backup "$script_dir/ops04-remote-backup.sh" "$latest_backup_dir"
run_gate "$temp_dir/alert-delivery.txt" '^alert_delivery_canary=PASS ' alert-delivery "$script_dir/ops04-alert-canary.sh"

"${compose[@]}" up -d db app
run_gate "$temp_dir/migration-manifest.txt" '^migration_manifest=PASS$' migration-manifest \
    "${compose[@]}" exec -T app php artisan ops:migrate --manifest-only --no-interaction
migration_manifest_hash="$("${compose[@]}" exec -T app sha256sum /var/www/html/release/migrations.manifest.json | awk '{print $1}')"
[[ "$migration_manifest_hash" == "$MIGRATION_MANIFEST_SHA" ]] || {
    printf 'runtime migration manifest is not the release manifest\n' >&2
    exit 1
}
"${compose[@]}" run --rm --env-from-file "$env_file" app sh -c 'DB_USERNAME="$DB_MIGRATION_USERNAME" DB_PASSWORD="$DB_MIGRATION_PASSWORD" php artisan ops:migrate --force --no-interaction'
"${compose[@]}" up -d scheduler web
run_gate "$temp_dir/readiness.txt" '^readiness_probe=PASS ' readiness "$script_dir/ops04-readiness.sh"
run_gate "$temp_dir/observability.txt" '^deploy_observability=PASS$' observability "$script_dir/ops04-deploy-observe.sh"
run_gate "$temp_dir/domain-tls.txt" '^domain_tls=PASS ' domain-tls "$script_dir/ops04-domain-tls.sh" live
run_gate "$temp_dir/synthetic.txt" '^synthetic=PASS ' synthetic "$script_dir/ops04-synthetic.sh"

readiness_status="$(sed -n 's/^readiness_probe=//p' "$temp_dir/readiness.txt" | head -n 1)"
observability_status="$(sed -n 's/^deploy_observability=//p' "$temp_dir/observability.txt" | head -n 1)"
remote_backup_status="$(sed -n 's/^remote_backup=\([^ ]*\).*/\1/p' "$temp_dir/remote-backup.txt" | head -n 1)"
alert_delivery_status="$(sed -n 's/^alert_delivery_canary=\([^ ]*\).*/\1/p' "$temp_dir/alert-delivery.txt" | head -n 1)"
tls_status="$(sed -n 's/^domain_tls=\([^ ]*\).*/\1/p' "$temp_dir/domain-tls.txt" | head -n 1)"
synthetic_status="$(sed -n 's/^synthetic=\([^ ]*\).*/\1/p' "$temp_dir/synthetic.txt" | head -n 1)"
scheduler_status="$(sed -n 's/^scheduler=\([^ ]*\).*/\1/p' "$temp_dir/readiness.txt" | head -n 1)"
[[ "$readiness_status" == PASS && "$observability_status" == PASS && "$remote_backup_status" == PASS \
    && "$alert_delivery_status" == PASS && "$tls_status" == PASS && "$synthetic_status" == PASS \
    && "$scheduler_status" == required-and-running ]] || {
    printf 'activation gates did not produce a complete PASS set\n' >&2
    exit 1
}
final_verdict=PASS

receipt_file="${receipt_file:-$repo_root/paid-pilot-activation-receipt.json}"
mkdir -p "$(dirname "$receipt_file")"
backup_reference="$(sed -n 's/^backup_id=//p' "$latest_manifest" | head -n 1)"
cat > "$receipt_file" <<EOF
{
  "timestamp_utc": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "rc_sha": "${APP_BUILD_SHA}",
  "head_sha": "${head_sha}",
  "app_image_digest": "${app_digest}",
  "web_image_digest": "${web_digest}",
  "app_image_revision": "${app_revision}",
  "web_image_revision": "${web_revision}",
  "migration_manifest_sha256": "${migration_manifest_hash}",
  "scribe_docs_sha256": "${scribe_hash}",
  "environment_identity": "${APP_DOMAIN}",
  "environment": "${APP_ENV}",
  "readiness": "${readiness_status}",
  "backup_reference": "${backup_reference}",
  "restore_reference": "${restore_reference}",
  "remote_backup": "${remote_backup_status}",
  "tls": "${tls_status}",
  "synthetic": "${synthetic_status}",
  "alert_channel_configured": "${alert_delivery_status}",
  "scheduler": "${scheduler_status}",
  "support_owner_id": "${support_owner}",
  "rpo_rto_accepted": "${PAID_PILOT_RPO_RTO_ACCEPTED:-no}",
  "human_approval_reference": "${human_approval}",
  "final_verdict": "${final_verdict}"
}
EOF
printf 'paid_pilot_activation=PASS\nreceipt=%s\n' "$receipt_file"
