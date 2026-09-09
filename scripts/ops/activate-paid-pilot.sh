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
    print_gate verify-rc "APP_BUILD_SHA from env + git provenance when checkout is available"
    print_gate destructive-guards "explicit PAID_PILOT_ALLOW_MUTATION and PAID_PILOT_ACTIVATION_CONFIRM"
    print_gate backup-prerequisite "$script_dir/ops04-backup-monitor.sh"
    print_gate remote-backup "$script_dir/ops04-remote-backup.sh <latest-local-backup>"
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
[[ "${APP_DOMAIN:-}" =~ ^[A-Za-z0-9.-]+$ ]] || {
    printf 'APP_DOMAIN must be a safe hostname for the receipt\n' >&2
    exit 1
}

if git -C "$repo_root" rev-parse --git-dir >/dev/null 2>&1; then
    git -C "$repo_root" cat-file -e "${APP_BUILD_SHA}^{commit}" || {
        printf 'release SHA is not present in the checkout\n' >&2
        exit 1
    }
fi

compose_file="${COMPOSE_FILE:-compose.production.yaml}"
compose_project="${COMPOSE_PROJECT_NAME:-ead2026-production}"
compose_env=(--env-file "$env_file")
compose=(docker compose "${compose_env[@]}" -f "$compose_file" -p "$compose_project")
temp_dir="$(mktemp -d)"
trap 'rm -rf "$temp_dir"' EXIT

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
"$script_dir/ops04-backup-monitor.sh"
latest_manifest="$(find "$backup_root" -type f -name manifest.txt -print0 | xargs -0r ls -1t | head -n 1)"
[[ -n "$latest_manifest" ]] || { printf 'latest backup manifest not found\n' >&2; exit 1; }
latest_backup_dir="$(dirname "$latest_manifest")"
"$script_dir/ops04-remote-backup.sh" "$latest_backup_dir"

"${compose[@]}" exec -T app php artisan ops:migrate --manifest-only --no-interaction > "$temp_dir/migration-manifest.txt"
migration_manifest_hash="$(sha256sum "$temp_dir/migration-manifest.txt" | awk '{print $1}')"
"${compose[@]}" run --rm app php artisan ops:migrate --force --no-interaction
"${compose[@]}" up -d db app scheduler web
"$script_dir/ops04-readiness.sh"
"$script_dir/ops04-deploy-observe.sh"
"$script_dir/ops04-domain-tls.sh" live
"$script_dir/ops04-synthetic.sh"

receipt_file="${receipt_file:-$repo_root/paid-pilot-activation-receipt.json}"
mkdir -p "$(dirname "$receipt_file")"
backup_reference="$(sed -n 's/^backup_id=//p' "$latest_manifest" | head -n 1)"
cat > "$receipt_file" <<EOF
{
  "timestamp_utc": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "rc_sha": "${APP_BUILD_SHA}",
  "migration_manifest_sha256": "${migration_manifest_hash}",
  "environment_identity": "${APP_DOMAIN}",
  "environment": "${APP_ENV}",
  "readiness": "PASS",
  "backup_reference": "${backup_reference}",
  "restore_reference": "${restore_reference}",
  "remote_backup": "PASS",
  "tls": "PASS",
  "synthetic": "PASS",
  "alert_channel_configured": "yes",
  "scheduler": "PASS",
  "support_owner_id": "${support_owner}",
  "rpo_rto_accepted": "${PAID_PILOT_RPO_RTO_ACCEPTED:-no}",
  "human_approval_reference": "${human_approval}",
  "final_verdict": "PASS"
}
EOF
printf 'paid_pilot_activation=PASS\nreceipt=%s\n' "$receipt_file"
