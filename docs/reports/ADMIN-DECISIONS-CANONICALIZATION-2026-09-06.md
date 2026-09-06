# Canonicalização das Decisões Admin — 2026-09-06

## 1. Decisions Canonicalized

As decisões do relatório `ADMIN-HUMAN-DECISIONS-2026-09-06.md` foram promovidas a contrato
documental para o trabalho Admin:

- **Assessment ownership:** Admin é operador tenant-wide; Instructor é owner pedagógico; criação
  Admin usa `instructor_id = null`; alteração administrativa não transfere ownership; eventual
  `assign/transfer` será operação explícita e auditável.
- **Categorias:** o vocabulário canônico é **System** e **Custom**. System é global/platform/MZRT;
  Custom é tenant-owned/Admin. Admin pode usar System e CRUD Custom; Instructor apenas lê/seleciona
  conforme permitido. System não é CRUD do tenant, Custom não colide semanticamente com System,
  Custom pode repetir entre tenants e a hierarquia preserva o mesmo scope/tenant.
- **Publication/readiness:** publicação Admin exige curso ativo, não archived, pelo menos um módulo
  e pelo menos uma lesson pertencente a módulo do curso, publicada e ativa. Curso sem Instructor é
  válido. Quiz, mídia e preço não são requisitos. `archived` é terminal no MVP para publish/unpublish.

Evidência desta tarefa: `STATIC_EVIDENCE_ONLY`. Foram executadas probes e validação estática do
harness; não houve execução de comportamento de produto contra app/banco.

## 2. Manifest Changes

Atualizado `.agents/capability-context.json`:

- removidos os gates `assessment-ownership`, `category-system-custom-naming` e
  `learning-publication-readiness`;
- adicionadas regras/invariantes explícitas para ownership Admin/Instructor, `instructor_id = null`,
  System/Custom, colisões/hierarquia e readiness mínimo;
- ampliados os matchers de prompt/path para Admin Assessment, Categories Admin e Course
  publish/readiness, inclusive em `docs/specs/*`;
- preservados como `HUMAN_DECISION_REQUIRED`: `quiz-core-advanced-boundary`, `media-provider`,
  `external-enrollment` e `plugin-lifecycle`.

## 3. Spec Changes

Atualizadas minimamente:

- `docs/specs/30-assessment/spec.md`;
- `docs/specs/30-assessment/subspecs/questionnaires-questions.md`;
- `docs/specs/20-catalog-learning/spec.md`;
- `docs/specs/20-catalog-learning/subspecs/catalog.md`;
- `docs/specs/20-catalog-learning/subspecs/courses-modules-lessons.md`.

As specs agora distinguem ownership operacional/pedagógico, fixam System/Custom e descrevem o
readiness de publicação sem afirmar que a implementação já existe.

## 4. Task Deltas

Mantidos em `Pending`, sem marcar decisão documental como implementação:

- `docs/specs/30-assessment/tasks.md`: superfície Admin tenant-wide, criação com `instructor_id =
  null` e eventual assign/transfer explícito;
- `docs/specs/20-catalog-learning/tasks.md`: validação de readiness no publish e transição explícita
  de publicação de lesson, além da convergência do contrato de leitura/Resource para System/Custom;
- os gaps estruturais de categoria já existentes (`normalized_name`, unicidade persistida,
  materialized path e parent de mesmo escopo) continuam pendentes.

## 5. Routing/Bundle Changes

O resolver existente `scripts/ai/capability-context.sh`, acionado pelo
`scripts/ai/skill-router.sh`, agora seleciona:

- `assessment` para prompts/paths de Assessment Admin e aplica ownership fechado sem gate humano;
- `categories` para Categories Admin e aplica System/Custom sem gate humano;
- `learning-course-content` para Course publish/readiness e aplica a regra mínima sem gate humano.

Não foi duplicado detalhe de domínio em `AGENTS.md` nem alterada a tabela geral de skills em
`.agents/skills/routing.json`; a seleção continua centralizada no capability context manifest.

## 6. Probe Evidence

Executado:

```text
bash scripts/ai/test-capability-context.sh
PASS: capability context probes passed (12 prompt scenarios + 3 tool scenarios; closed Admin decisions are discriminated from deferred gates)
```

As probes cobrem os três contratos fechados, ausência dos três gates removidos, caminhos de specs de
Categories/Course, Assessment Admin por path e a permanência dos gates diferidos relevantes.

Checks complementares:

- `python3 scripts/ai/validate-harness.py`: passou, com o warning preexistente de
  `.opencode/opencode.json` ausente;
- `git diff --check`: passou;
- `bash scripts/ai/verify-changes.sh`: passou com 10 arquivos de `tests/Architecture`.

## 7. Deferred Decisions

Continuam `HUMAN_DECISION_REQUIRED` no manifest:

- fronteira detalhada entre quiz core e advanced/plugin;
- MediaProvider;
- matrícula externa;
- lifecycle de plugins.

Instructor/Student e as decisões diferidas não foram iniciados.

## 8. Remaining Product Implementation

Nenhum comportamento PHP, migration, endpoint ou configuração de produto foi alterado nesta tarefa.
Continuam gaps de implementação Admin:

- implementar/convergir a superfície Admin de Assessment com ownership tenant-wide e `instructor_id
  = null`;
- implementar readiness no `PublishCourseAction` e a transição explícita de publish de lesson;
- concluir os deltas estruturais e de contrato System/Custom de categorias;
- adicionar testes Feature/E2E de produto quando cada slice for autorizado.

## 9. Verdict

`ADMIN_DECISIONS_CANONICALIZED_WITH_GAPS`

As três decisões solicitadas estão canonicalizadas nas fontes de contexto, specs, tasks e probes.
Os gaps são exclusivamente de implementação futura e permanecem visíveis como `Pending`.
