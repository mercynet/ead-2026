# Commercial v0.1 — OPS01B Git Decomposition — 2026-09-09

## 1. Baseline

Proveniência capturada antes de qualquer alteração nesta sessão:

| Item | Resultado |
|---|---|
| Branch | `main` |
| HEAD | `9513d1efccc0bc5be2d4336eb4952badd1ef3196` |
| `git status --short` | vazio |
| staged / unstaged / untracked | 0 / 0 / 0 |
| total dirty exato | 0 |
| `git diff --stat` | vazio |
| `git diff --name-status` | vazio |
| `git diff --cached --name-status` | vazio |
| push | `HEAD == origin/main` no início |

O estado descrito no pedido (aproximadamente 124 entradas dirty, sem commit) não era o estado
observável desta sessão. O snapshot histórico mais próximo disponível em
`docs/reports/OPS-01-EVIDENCE-2026-09-09.md` registra 123 paths em `8df531f`; depois disso a
trajetória foi consolidada em `9533b50` e `9513d1e`. O `git status` histórico path a path não é
recuperável sem inventar dados.

## 2. Verify-Changes Failure

`scripts/ai/verify-changes.sh` lê `git status --porcelain --untracked-files=all`, mapeia os paths
para `tests/Architecture` e chama exatamente `./vendor/bin/sail artisan test --compact <tests>`.
Com a árvore limpa, sai 0 antes de chamar Sail e seleciona zero testes. Para exercitar o ramo
relevante, um probe PHP descartável em uma migration foi criado e removido na mesma sessão. A
execução canônica no sandbox saiu 2 com `Docker is not running.`; o comando equivalente por
`docker exec` passou. A mesma linha canônica executada fora do sandbox, com acesso ao socket, saiu 0
com os 3 testes selecionados verdes.

## 3. Root Cause

`vendor/laravel/sail/bin/sail` executa `docker info > /dev/null 2>&1` antes do Artisan. O processo
padrão deste agente resolve `/usr/bin/docker`, tem `DOCKER_HOST` e `DOCKER_CONTEXT` não definidos,
usa o contexto `default` e o endpoint `unix:///var/run/docker.sock`. O socket é `nobody:nogroup`,
modo `0660`; no subprocesso padrão o acesso é negado com `permission denied while trying to
connect to the docker API`. Sail reduz esse erro à mensagem genérica `Docker is not running`.

Não foi encontrada diferença de alias, PATH, contexto ou profile pessoal; bash não interativo e
`sh` reproduzem o bloqueio. `docker exec`/`docker compose` funcionam quando autorizados fora do
limite do sandbox, o que não dá ao subprocesso do wrapper a mesma autoridade. Não foi aplicado fix:
usar `SAIL_SKIP_CHECKS`, retry ou alias mascararia a falha e quebraria a equivalência local/CI. A
causa é ambiental/de autorização do subprocesso, não Sail, script de seleção ou produto.

## 4. Canonical Gate Result

Classificação: **`VERIFY_CANONICAL_WRAPPER_FAILURE`**.

Os testes equivalentes históricos e a execução autorizada fora do sandbox são evidência auxiliar,
não promoção do receipt padrão para `VERIFY_CANONICAL_PASS`. A execução sobre a árvore limpa é um
no-op e não prova invariantes do produto.

## 5. Dirty Path Inventory

Inventário integral do working tree observado: **0 paths**.

| Categoria | Paths dirty observados | Contagem |
|---|---|---:|
| A — OPS_HARDENING | nenhum | 0 |
| B — INSTRUCTOR_ASSESSMENT | nenhum | 0 |
| C — STUDENT_LEARNING | nenhum | 0 |
| D — CORE_ADMIN_MZRT | nenhum | 0 |
| E — DOCS_EVIDENCE | nenhum no baseline | 0 |
| F — GENERATED | nenhum untracked versionável | 0 |
| G — TEMPORARY | nenhum untracked | 0 |
| H — UNRELATED / PREEXISTING | nenhum | 0 |
| I — INCONCLUSIVE | nenhum path atual; snapshot histórico não recuperável path a path | 0 |

Reconciliação lógica do commit histórico `9533b50` (não é dirty atual):

- A: `.env.e2e.example`, `app/Console/Commands/{E2eRunCommand,QaFreshDatabaseCommand}.php`,
  `app/Shared/Database/**`, `app/Shared/Documentation/Scribe/**`, `bootstrap/app.php`, config,
  seeders, `tests/Feature/Console/**`, `tests/e2e-http/**` e `scripts/ai/pre-tool-use.sh`.
- B: `app/Modules/Assessment/**` voltado a Instructor, Learning Instructor, testes de Assessment
  Instructor e `tests/e2e-http/instructor/**`.
- C: Learning Student (`Actions`, controllers, resources, policy, rotas),
  `CourseCommercialReadiness.php`, `Lesson.content`, testes Student e specs e2e Student.
- D: demais mudanças de Learning/Admin e testes de suporte; MZRT/Admin anteriores permanecem nos
  commits `07f0bbc` e `8df531f`.
- E: `docs/STATE.md`, relatórios comerciais/instructor/student/OPS, tasks, baseline/configuração e
  testes arquiteturais de evidência.
- F/G/H/I: nenhum path versionável dirty; artefatos locais estão ignorados e fora do stage.

## 6. Classification

Não existe material dirty para classificar ou separar. A tabela acima é a classificação completa do
working tree atual; os grupos históricos só explicam como a entrega já commitada se relaciona ao
pedido.

## 7. Dependency Graph

OPS runner/safety → Instructor Assessment e Student Learning → contratos/evidência.
Student depende de `Lesson.content`; consumidores Assessment/Financial dependem dos contratos
Learning; o runner é independente do domínio. `9533b50` agrupou essas dependências de forma
coerente. Separação retroativa exigiria reescrita de histórico publicado, não autorizada.

## 8. Migration Reconciliation

- Há 73 migrations no manifesto atual.
- `app/Modules/Learning/Database/Migrations/2026_09_08_120000_add_content_to_lessons_table.php`
  está tracked, foi introduzida em `9533b50d9e83802dff9add75d03448c4fef74dc6` e tem blob
  `a0a7b780989b013cbb179c13132978cababe0e06`.
- `docker exec ead2026-laravel.test-1 php artisan migrate:status --no-interaction` mostrou a
  migration como `[1] Ran`.
- Não há duplicata desse nome/timestamp. `2026_02_21_142007` e `2026_07_07_221108` aparecem em
  pares de nomes distintos e já são esperados no manifesto.
- Nenhuma migration executada foi encontrada fora de Git.

## 9. Commit Plan

1. `07f0bbc feat(admin): close admin operations and evidence`.
2. `8df531f docs(admin): record published closure`.
3. `9533b50 feat: close commercial API capability slices` — OPS, Instructor, Student, contratos,
   migration, testes e evidência acumulada.
4. `9513d1e chore: record pushed session state`.
5. Um commit docs-only desta sessão para este relatório e `docs/STATE.md`, sem produto e sem push;
   criado localmente com exatamente esses dois paths.

Não serão criados commits artificiais A/B/C: não há paths dirty e a implementação já foi publicada
em um commit coerente. Nenhum reset, reescrita, tag, merge ou push foi feito.

## 10. Stage Review

No baseline: stage dry run vazio, 0 paths. O stage dry run deste fechamento conteve exatamente
`docs/STATE.md` e `docs/reports/COMMERCIAL-V0.1-OPS01B-GIT-DECOMPOSITION-2026-09-09.md`.
Risco baixo: somente documentação/evidência; nenhum código, migration, config ou teste entra.

## 11. Commits Created

Antes desta reconciliação: nenhum commit criado nesta sessão. Foi criado um único commit docs-only
no fechamento; os commits de produto anteriores permanecem intactos.

## 12. Regression

Receipts preservados e confirmados no estado/reports: Architecture 37/37 e 1.338 assertions;
focais comerciais 76/76 e 1.087 assertions; `qa:fresh` em `testing` PASS; `git diff --check` PASS;
73/73 migrations em fresh/E2E; migration candidata `[1] Ran`; `verify-changes.sh` wrapper failure
no sandbox e equivalente via `docker exec` verde. Como esta sessão não altera produto, não há
subset de código adicional causado pelo commit docs-only.

## 13. Final Working Tree

O baseline e o estado final são clean. `main` está um commit local à frente de `origin/main`; não
houve push.

## 14. Remaining Risks

Backup/restore, deploy/rollback, storage produtivo, TLS/secrets, monitoring/alertas e rehearsal
operacional continuam pendentes. O wrapper canônico exige uma execução com autoridade de socket
equivalente à CI. Avisos Scribe `bodyParameters()` são conhecidos; não foram alterados.

## 15. RC Verdict

**`RC_CHECKPOINTED_LOCAL`** para o candidato funcional já commitado e documentado localmente. Isso
não é `production ready`; Paid Pilot permanece **`NOT_READY`** até os blockers operacionais serem
comprovados.
