#!/usr/bin/env bash
# Discriminant probes for capability context selection. No app or database required.
set -euo pipefail

script_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
root=$(cd "$script_dir/../.." && pwd)
router="$root/scripts/ai/skill-router.sh"

failures=0

prompt_context() {
    jq -n --arg prompt "$1" '{prompt: $prompt}' | "$router" prompt
}

tool_context() {
    jq -n --arg path "$1" --arg command "$2" '{tool_input: {file_path: $path, command: $command}}' | "$router" tool
}

assert_contains() {
    local label=$1
    local haystack=$2
    local needle=$3
    if ! grep -Fq -- "$needle" <<<"$haystack"; then
        echo "FAIL: $label — missing '$needle'" >&2
        failures=$((failures + 1))
    fi
}

assert_not_contains() {
    local label=$1
    local haystack=$2
    local needle=$3
    if grep -Fq -- "$needle" <<<"$haystack"; then
        echo "FAIL: $label — unexpected '$needle'" >&2
        failures=$((failures + 1))
    fi
}

assert_bundle() {
    local label=$1
    local output=$2
    local bundle=$3
    assert_contains "$label" "$output" "[$bundle]"
}

categories=$(prompt_context "alterar categoria no Admin")
assert_bundle "categoria Admin" "$categories" "categories"
assert_contains "categoria ownership" "$categories" "System é global"
assert_contains "categoria custom ownership" "$categories" "Custom pertence ao tenant"
assert_not_contains "categoria Admin sem decisão humana" "$categories" "HUMAN_DECISION_REQUIRED: category-system-custom-naming"

category_naming=$(prompt_context "decidir nomenclatura System versus DEFAULT para categorias")
assert_bundle "categoria nomenclatura" "$category_naming" "categories"
assert_contains "categoria nomenclatura canônica" "$category_naming" "contrato canônico atual é System/Custom"
assert_not_contains "categoria nomenclatura sem decisão humana" "$category_naming" "HUMAN_DECISION_REQUIRED: category-system-custom-naming"

admin_course=$(prompt_context "criar endpoint Admin Course")
assert_bundle "Admin Course" "$admin_course" "learning-course-content"
assert_contains "Admin Course ownership" "$admin_course" "Admin tem ownership operacional"
assert_contains "Admin Course hierarchy" "$admin_course" "Course → Module → Lesson"
assert_contains "Admin Course readiness" "$admin_course" "curso ativo, não archived, módulo e lesson publicada/ativa"
assert_not_contains "Admin Course sem decisão humana" "$admin_course" "HUMAN_DECISION_REQUIRED: learning-publication-readiness"

course_publish=$(prompt_context "implementar publicação Admin de curso com readiness mínimo")
assert_bundle "Course publish" "$course_publish" "learning-course-content"
assert_contains "Course publish readiness" "$course_publish" "Readiness mínimo não exige Instructor, quiz, mídia ou preço"
assert_not_contains "Course publish sem decisão humana" "$course_publish" "HUMAN_DECISION_REQUIRED: learning-publication-readiness"

media=$(prompt_context "adicionar novo tipo de mídia de LessonMedia")
assert_bundle "novo tipo de mídia" "$media" "learning-course-content"
assert_contains "MediaProvider gate" "$media" "HUMAN_DECISION_REQUIRED: media-provider"
assert_contains "media multiplicity" "$media" "múltiplos registros"

admin_quiz=$(prompt_context "criar quiz no Admin")
assert_bundle "quiz Admin" "$admin_quiz" "assessment"
assert_contains "quiz core" "$admin_quiz" "Quiz simples pertence ao core"
assert_contains "quiz Admin ownership" "$admin_quiz" "Admin é operador tenant-wide"
assert_contains "quiz Admin null instructor" "$admin_quiz" "instructor_id = null"
assert_not_contains "quiz Admin sem decisão humana" "$admin_quiz" "HUMAN_DECISION_REQUIRED: assessment-ownership"
assert_not_contains "quiz Admin não é advanced por default" "$admin_quiz" "[ecosystem-plugins]"

advanced_quiz=$(prompt_context "implementar quiz avançado")
assert_bundle "quiz avançado Assessment" "$advanced_quiz" "assessment"
assert_bundle "quiz avançado plugin" "$advanced_quiz" "ecosystem-plugins"
assert_contains "quiz advanced gate" "$advanced_quiz" "HUMAN_DECISION_REQUIRED: quiz-core-advanced-boundary"
assert_contains "quiz plugin classification" "$advanced_quiz" "CORE SIMPLE + PLUGIN ADVANCED"

tenant_gateway=$(prompt_context "configurar gateway do tenant")
assert_bundle "gateway tenant Financial" "$tenant_gateway" "financial"
assert_bundle "gateway tenant Ecosystem" "$tenant_gateway" "ecosystem-plugins"
assert_contains "gateway ownership" "$tenant_gateway" "Gateway do tenant é diferente"

new_plugin=$(prompt_context "criar novo plugin first-party")
assert_bundle "novo plugin" "$new_plugin" "ecosystem-plugins"
assert_contains "plugin stores" "$new_plugin" "Activation, entitlement e tenant config são conceitos/stores distintos"
assert_contains "plugin lifecycle gate" "$new_plugin" "HUMAN_DECISION_REQUIRED: plugin-lifecycle"

instructor_course=$(prompt_context "alterar Course do Instructor")
assert_bundle "Instructor Course" "$instructor_course" "learning-course-content"
assert_contains "Instructor boundary" "$instructor_course" "Admin tem ownership operacional"

enrollment_payment=$(prompt_context "corrigir fluxo de matrícula e pagamento")
assert_bundle "matrícula Learning" "$enrollment_payment" "learning-course-content"
assert_bundle "matrícula Financial" "$enrollment_payment" "financial"
assert_contains "matrícula cross-domain" "$enrollment_payment" "Matrícula, acesso, progresso"
assert_contains "ledger distinction" "$enrollment_payment" "Student → tenant ledger é diferente"

external_enrollment=$(prompt_context "corrigir matrícula externa")
assert_bundle "matrícula externa Learning" "$external_enrollment" "learning-course-content"
assert_bundle "matrícula externa Financial" "$external_enrollment" "financial"
assert_contains "matrícula externa gate Learning" "$external_enrollment" "HUMAN_DECISION_REQUIRED: external-enrollment"

category_task_path=$(tool_context "docs/specs/20-catalog-learning/subspecs/catalog.md" "ajustar Categories Admin")
assert_bundle "path Categories" "$category_task_path" "categories"
assert_not_contains "path Categories sem decisão humana" "$category_task_path" "HUMAN_DECISION_REQUIRED: category-system-custom-naming"

course_task_path=$(tool_context "docs/specs/20-catalog-learning/tasks.md" "ajustar Course publish readiness")
assert_bundle "path Course publish" "$course_task_path" "learning-course-content"
assert_not_contains "path Course publish sem decisão humana" "$course_task_path" "HUMAN_DECISION_REQUIRED: learning-publication-readiness"

tool_probe=$(tool_context "app/Modules/Assessment/Routes/admin.php" "implementar quiz avançado")
assert_bundle "tool path Assessment" "$tool_probe" "assessment"
assert_bundle "tool command Ecosystem" "$tool_probe" "ecosystem-plugins"
assert_contains "tool Assessment ownership canonical" "$tool_probe" "Admin é operador tenant-wide"
assert_not_contains "tool Assessment sem decisão humana" "$tool_probe" "HUMAN_DECISION_REQUIRED: assessment-ownership"

if [ "$failures" -ne 0 ]; then
    echo "FAIL: $failures capability context probe(s) failed" >&2
    exit 1
fi

echo "PASS: capability context probes passed (12 prompt scenarios + 3 tool scenarios; closed Admin decisions are discriminated from deferred gates)"
