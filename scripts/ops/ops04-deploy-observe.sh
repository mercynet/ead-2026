#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
failures=0

run_check() {
    local name="$1"
    shift
    printf 'check=%s\n' "$name"
    if ! "$@"; then
        failures=$((failures + 1))
        printf 'check=%s status=FAIL\n' "$name" >&2
    fi
}

run_check readiness "$script_dir/ops04-readiness.sh"
run_check synthetic "$script_dir/ops04-synthetic.sh"
run_check backup "$script_dir/ops04-backup-monitor.sh"
run_check error_scan "$script_dir/ops04-error-scan.sh"

if (( failures > 0 )); then
    printf 'deploy_observability=FAIL failures=%s\n' "$failures" >&2
    exit 1
fi

printf 'deploy_observability=PASS\n'
