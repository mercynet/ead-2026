#!/usr/bin/env bash
# Stop hook: fecha o loop dos guardrails executáveis.
#
# Mapeia o que mudou no working tree para os invariantes de tests/Architecture que
# arbitram aquele arquivo e roda SÓ esses (suite inteira = ~17s; subconjunto = ~1-3s).
# Falha → exit 2: o agente não encerra o turno com invariante vermelho.
# Também mantém o grafo do graphify atualizado (AST-only, sem custo de API).
set -uo pipefail

script_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
root="${CLAUDE_PROJECT_DIR:-$(cd "$script_dir/../.." && pwd)}"
cd "$root" 2>/dev/null || exit 0

input=$(cat)

# Evita laço: se já bloqueamos e o agente voltou a parar, não bloqueie de novo.
if command -v jq >/dev/null 2>&1; then
    [ "$(printf '%s' "$input" | jq -r '.stop_hook_active // false' 2>/dev/null)" = "true" ] && exit 0
fi

command -v git >/dev/null 2>&1 || exit 0

# Nomes de arquivo do working tree (inclui staged e untracked; trata rename).
changed=$(git status --porcelain --untracked-files=all 2>/dev/null | sed -E 's/^.{3}//; s/^.* -> //')
[ -z "${changed//[[:space:]]/}" ] && exit 0

php_touched=false
tests=()

add_test() {
    local candidate="tests/Architecture/$1.php"
    [ -f "$candidate" ] || return 0
    for existing in ${tests[@]+"${tests[@]}"}; do
        [ "$existing" = "$candidate" ] && return 0
    done
    tests+=("$candidate")
}

while IFS= read -r file; do
    [ -z "$file" ] && continue

    case "$file" in
    *.php) php_touched=true ;;
    esac

    # Superfície de rota / middleware / envelope central
    case "$file" in
    app/Modules/*/Routes/*.php | bootstrap/app.php | app/Modules/Core/Http/Middleware/*.php | app/Modules/Core/Enums/Area.php)
        add_test AreaRouteGuardTest
        add_test RouteSecuritySurfaceTest
        add_test ScribeAuthAnnotationMatchesMiddlewareTest
        ;;
    esac

    # RBAC
    case "$file" in
    config/permissions.php | app/Modules/*/Policies/*.php | app/Modules/*/Providers/*.php | app/Modules/Core/Models/User.php | database/seeders/PermissionsSeeder.php | database/seeders/RolesSeeder.php)
        add_test PermissionDriftTest
        add_test PermissionMetadataShapeTest
        ;;
    esac

    # Controller fino + envelope de erro
    case "$file" in
    app/Modules/*/Http/Controllers/*.php)
        add_test ControllerLeannessTest
        add_test ErrorEnvelopeShapeTest
        ;;
    esac

    # Schema: dinheiro em cents + escopo de tenant
    case "$file" in
    app/Modules/*/Database/Migrations/*.php | database/migrations/*.php)
        add_test MoneyNeverFloatTest
        add_test TenantScopingTest
        ;;
    esac

    # Model novo/alterado: escopo de tenant e PII auditado
    case "$file" in
    app/Modules/*/Models/*.php | config/lgpd.php)
        add_test TenantScopingTest
        add_test PiiAuditTest
        ;;
    esac

    # Fronteira de módulo vale para qualquer PHP de módulo
    case "$file" in
    app/Modules/*.php | app/Modules/*/*.php | app/Modules/*/*/*.php | app/Modules/*/*/*/*.php | app/Modules/*/*/*/*/*.php)
        add_test ModuleBoundaryTest
        ;;
    esac
done <<<"$changed"

# Grafo em dia sem custo de API — assíncrono para não somar latência ao turno.
if [ "$php_touched" = true ] && command -v graphify >/dev/null 2>&1; then
    (graphify update . >/dev/null 2>&1 &) >/dev/null 2>&1
fi

[ ${#tests[@]} -eq 0 ] && exit 0
[ -x ./vendor/bin/sail ] || exit 0

output=$(./vendor/bin/sail artisan test --compact "${tests[@]}" 2>&1)
status=$?

if [ $status -eq 0 ]; then
    echo "Invariantes do diff verdes (${#tests[@]} arquivo(s) de tests/Architecture)."
    exit 0
fi

{
    echo "INVARIANTE VERMELHO — o diff atual viola um guardrail executável. Corrija antes de encerrar:"
    printf '%s\n' "$output" | tail -40
    echo
    echo "Rodar de novo: ./vendor/bin/sail artisan test --compact ${tests[*]}"
} >&2

exit 2
