# Global Adversarial Commercial Validation — 2026-09-08

## 1. Executive Verdict

O working tree não sustenta as claims acumuladas de fechamento. Há boa cobertura Feature e Architecture, e a superfície canônica de Student/Instructor/Admin mostra sinais reais de implementação, mas a evidência comercial independente falha em dois pontos decisivos:

1. o provisioning HTTP real de tenant/MZRT falha com `MissingAppKeyException` no runtime atual;
2. o S-02 reportado como jornada comercial é fixture-assisted: cria usuários, cursos negativos, matrículas e o order/payment diretamente pelo banco, e apenas consulta o tenant já criado pelo runner.

Além disso, a superfície legada de Assessment oferece operações Student fora da área Student e inicia tentativa sem validar enrollment/acesso ao parent Course. Portanto, a conclusão global é rejeitada.

## 2. Provenance

- HEAD: `8df531fbc826c79aa4073dfbb70ee7a191ad7cb7`.
- Branch: `main`.
- Estado inicial: working tree dirty; 94 paths no `git status --porcelain=v1`; nenhum staged diff.
- Tracked modified no snapshot: 18 paths. `docs/STATE.md` já estava dirty e não foi tocado.
- Migrations encontradas: 65 arquivos em `app/Modules/*/Database/Migrations`, não 73 como consta em relatórios anteriores.
- Fingerprint do status inicial: `41a5a1c0dbe98214d9274d6ec4ce6e3d866c48a1b4816844c1f0d38bd2b8a2ff`.
- Fingerprint de `git diff HEAD --binary` no snapshot: `d5601c7602e915f4fe8d39542fd749d562524375f8ea6bf5ce3b6c9b0ab7b234`.
- Runtime local: `ead2026-laravel.test-1`, Laravel 12.63/PHP 8.4.18, publicado em `8099`.
- Runtime E2E: `ead2026-e2e-laravel.test-1`, MySQL descartável `ead2026_e2e`, listener usado pelos testes em `8080`.
- O `.env.e2e.example` não define `APP_KEY`; o container E2E atual também não apresentou uma configuração de chave funcional no fluxo de provisioning.
- Nenhuma conclusão abaixo é tratada como “SHA do produto”; ela pertence ao filesystem dirty identificado pelos fingerprints acima.

## 3. Harness Integrity

**Veredict: HARNESS_PARTIALLY_TRUSTWORTHY / EVIDENCE_WEAK.**

Não encontrei `markTestSkipped`, `->skip`, `only` ou `todo` ativos nas buscas globais relevantes. Também não encontrei runner que retorne sucesso após uma exceção de caso: o runner incrementa falhas de caso, 5xx inesperado e cleanup.

O problema é outro: o harness aceita setup privilegiado no banco e o próprio S-02 usa esse mecanismo para fabricar fatos que a jornada deveria produzir. Em `tests/e2e-http/learning/student-s02-commercial-integrated.php:28-76`, Instructor B, Student B, foreign Student e os cenários `pending/expired/cancelled` são criados diretamente por factories. Em `:108-133`, `Order`, `OrderItem` e `Payment` também são criados diretamente. O primeiro caso, `:80-92`, só faz `GET` de entitlements para o tenant que o `E2eRunCommand::bootFixtures()` já inseriu em `:193-227`.

O runner faz `migrate:fresh`, mas ignora o booleano de retorno em `app/Console/Commands/E2eRunCommand.php:80-82`; uma falha de migration não aborta imediatamente. O canário posterior reduz parte desse risco, mas não transforma o setup privilegiado em prova de jornada.

Conclusão: os testes in-process são úteis e verdes; os receipts E2E devem ser lidos como HTTP real com fixtures privilegiadas, não como prova independente de bootstrap, provisioning ou side effects de ponta a ponta.

## 4. MZRT Validation

As rotas canônicas `/api/v1/mzrt/*` carregam `auth:sanctum` e `area.guard:mzrt`; a suite Architecture passou. Porém, a reprodução independente:

```text
php artisan e2e:run mzrt/tenant-lifecycle --base=http://localhost:8080 --fresh
```

terminou com exit 1: `1 passou, 1 falhou`. O POST de criação/provisionamento de tenant respondeu 500. O stack aponta `EcosystemDefaultGatewayProvisioner.php:44`, chamado por `ProvisionTenantAction.php:101`, durante a criação de `TenantPluginConfig`; o erro efetivo foi `MissingAppKeyException`.

O S-02 não corrige essa lacuna: seu caso “MZRT confirma tenant provisionado” é apenas leitura de entitlements de um tenant fabricado pelo runner.

**Verdict: MZRT_REJECTED.**

## 5. Admin Validation

Há evidência Feature/Architecture para Course, Module, Lesson, Media, publicação, enrollment manual/cash e autorização tenant-wide. O S-02 também executa HTTP real para criação/publicação e confirmação manual, com negativos de tenant/persona.

Contudo, a preparação do tenant e parte do estado financeiro não passam pelo caminho HTTP. Não houve fresh-install comercial reproduzível a partir de MZRT; portanto, não foi possível fechar people/tenancy, membership e enrollment como uma jornada independente. Assessment Admin também continua documentado em parte na superfície legacy `/api/v1/assessment/*`, sem `area.guard:admin`, apesar de existirem rotas Admin novas.

**Verdict: ADMIN_EVIDENCE_WEAK.**

## 6. Instructor Validation

`instructor/i04-consolidated-closure` terminou `27 passou, 0 falhou`, e a suite Feature inclui ownership, foreign parent, results e testes de query bounded. A implementação canônica mostra políticas transitivas para Course/Module/Lesson/Media/Material e Results.

O I-04, entretanto, também depende de fixtures criadas pelo spec/runner; não prova provisioning por MZRT nem uma cadeia independente Admin → Instructor. A evidência de runtime é portanto qualificada, não uma confirmação de closure.

**Verdict: INSTRUCTOR_EVIDENCE_WEAK.**

## 7. Student Validation

O S-02 terminou `28 passou, 0 falhou` e cobriu My Courses, árvore, Lesson, Media/Material, progress, monotonicidade, replay, Student B, tenant B, estados pending/expired/cancelled, 401 e guards. Os Resources canônicos Student não expõem `tenant_id`, `instructor_id`, `file_path`, `storage_path`, `provider_ref` ou metadata interna; o URL de mídia é resolvido por `Student/LessonMediaResource`.

A evidência não é independente pelos motivos do harness. Adicionalmente, Student não é realmente “sem Assessment”: a rota legacy `/api/v1/assessment/attempts/*` concede `assessment.attempts.create/answer/finish` a `student` e não possui guard de área Student. `StartAttemptAction` valida tenant e `is_active`, mas não enrollment, publicação/atividade do Course parent ou acesso do Student ao parent.

**Verdict: STUDENT_EVIDENCE_WEAK.**

## 8. Cross-Persona Matrix

| Actor | MZRT | Admin | Instructor | Student |
|---|---|---|---|---|
| Developer | guard MZRT canônico; provisioning HTTP falhou | acesso depende da matriz existente | acesso depende da matriz existente | acesso depende da matriz existente |
| Admin | deve ser negado pelo `area.guard:mzrt` | guard canônico presente | guard canônico impede superfície Instructor | guard canônico impede superfície Student |
| Instructor | deve ser negado pelo `area.guard:mzrt` | guard canônico impede superfície Admin | guard canônico presente; ownership transitivo testado | guard canônico impede superfície Student |
| Student | deve ser negado pelo `area.guard:mzrt` | guard canônico impede superfície Admin | guard canônico impede superfície Instructor | guard canônico presente; ownership/tenant testados |

Essa matriz é estática/Feature para as rotas novas. Ela não é globalmente selada porque as superfícies domain-first `/api/v1/learning/*` e `/api/v1/assessment/*` continuam sem `area.guard` e a segunda expõe Assessment Student.

## 9. Legacy Bypass Audit

Inventário encontrado:

- Learning legacy: `/api/v1/learning/*`, sem `area.guard`, incluindo Course/Module/Lesson/Enrollment/Progress/Media.
- Assessment legacy: `/api/v1/assessment/*`, sem `area.guard`, incluindo attempts e certificates.
- Financial legacy/compatibility e superfícies novas coexistem; o checkout canônico Student possui guard Student.

O bypass confirmado é Assessment: uma persona Student com matrícula inexistente ou expirada pode, em princípio, iniciar tentativa de questionário ativo do mesmo tenant se conhecer o ID. A rota só exige autenticação, `tenant.access` e permission; a Action não consulta Enrollment. Isso é uma operação Student efetiva fora da superfície canônica e fora do contrato “Assessment não iniciado/condicional”.

Learning legacy tem proteção adicional por policies/actions em vários mutators, portanto não classifiquei toda a árvore como exploit confirmado. Ainda assim, sua ausência de area guard permite que equivalentes continuem acessíveis pela superfície errada e mantém um bypass de contrato/área.

**Verdict: LEGACY_BYPASS_HIGH_RISK.**

## 10. Tenant Escape

As Actions canônicas Student, Instructor e Admin verificadas usam tenant explícito e as Feature/E2E cobrem foreign Course, Module, Lesson, Media, Material, Enrollment, Questionnaire/Results e tenant B. Nenhum vazamento cross-tenant foi observado nos testes executados.

Há, porém, uma limitação estrutural: várias tabelas têm FK simples para `tenant_id` e FK simples para o parent, sem FK composto que obrigue a coerência `child.tenant_id = parent.tenant_id`. Isso aparece, entre outras, em `course_modules`, `lessons`, `enrollments`, `lesson_progress`, `course_materials`, `quiz_attempts` e `quiz_attempt_answers`. A aplicação faz a maior parte da contenção; o banco permite corrupção de coerência se um caminho futuro falhar.

**Verdict: TENANT_ISOLATION_FEATURE_VERIFIED, NOT_GLOBALLY_CONFIRMED.**

## 11. Payload Spoofing

As requests canônicas não aceitam livremente `tenant_id`, ownership ou user actor para Course/Module/Lesson/Progress; o S-02 e as Feature tests cobrem spoof de tenant, owner, parent e progress de outro usuário. Não foi observado efeito parcial nos caminhos exercitados.

O ponto adversarial material é diferente: `StoreCourseRequest` e `UpdateCourseRequest` aceitam `certificate_enabled`, `certificate_requires_quiz`, `certificate_min_progress` e `certificate_min_score`. Esses campos tornam configurável uma capacidade que o Student comercial não fechou. Não há gate de release que impeça um Course vendido de exigir Assessment/certificate não suportado.

## 12. Database Invariants

Confirmados estaticamente e no migrate fresh: FKs principais, unique de progress por enrollment/lesson, unique de media progress, unique de outbox por order/event e unique current enrollment via coluna gerada.

Pontos de atenção:

- coerência entre `tenant_id` e parents não é garantida de forma composta na maior parte do Learning/Assessment;
- `quiz_attempts` e respostas mantêm tenant/user/questionnaire em FKs independentes;
- a unicidade de enrollment current depende da migration SQL específica para MySQL;
- há dois arquivos de migration com o timestamp `2026_07_07_221108`, o que é executável hoje, mas frágil para ordenação humana e manutenção.

**Classificação: MEDIUM concern de integridade, sem escape reproduzido.**

## 13. Migration / Fresh Install

Executado em banco E2E descartável:

```text
php artisan migrate:fresh --force       (via S-02, MZRT e I-04: migration concluída)
php artisan migrate:rollback --step=1 --force && php artisan migrate --force
```

O rollback/reapply do último migration terminou exit 0, assim como os três fresh migrations. Não executei rollback completo de todas as 65 migrations; portanto não atribuo a elas uma garantia de reversibilidade total.

O fresh install funcional foi rejeitado: migrations sobem, mas o passo mínimo de provisioning MZRT quebra em 500 por `APP_KEY` ausente. Isso também torna o runtime E2E atual não adequado como prova de bootstrap comercial.

**Verdict: MIGRATIONS_PASS_WITH_CONCERNS; FRESH_INSTALL_REJECTED.**

## 14. Commercial E2E

O comando:

```text
php artisan e2e:run learning/student-s02-commercial-integrated --base=http://localhost:8080 --fresh
```

terminou exit 0 com `28 passou, 0 falhou`, mas não é uma reprodução independente: tenant/admin/instructor/student base vêm de `bootFixtures`, negativos vêm de factories no `setup`, e order/payment vêm de `capture` antes do POST de confirmação. Não prova que o produto cria todos esses efeitos a partir das APIs comerciais.

A tentativa:

```text
php artisan e2e:run mzrt/tenant-lifecycle --base=http://localhost:8080 --fresh
```

terminou exit 1. Não existe, portanto, uma jornada completa atual MZRT → Admin → Instructor → Student confirmada sem assistência privilegiada.

**Verdict: COMMERCIAL_JOURNEY_REJECTED.**

## 15. Side Effects

O S-02 consulta side effects e confirma enrollment, progress, outbox e cleanup; não deixou duplicações observadas no receipt. Porém, `orders`, `order_items` e `payments` principais são inseridos diretamente pelo spec (`student-s02-commercial-integrated.php:108-133`), e não pelo fluxo que está sendo validado.

Assim, não há prova independente de que a jornada produza exatamente os tenants, memberships, course/content, enrollment, order/payment/outbox e progress esperados. O resultado correto é “sem anomalia observada no fixture-assisted run”, não “financial side effects confirmados”.

**Verdict: FINANCIAL_SIDE_EFFECTS_NOT_CONFIRMED.**

## 16. Security

Passes relevantes: area guards canônicos, tenant access e resources Student sem paths de storage; o S-02 exercita Student foreign e URL/media autorizada. O URL Student canônico é derivado depois da autorização e não expõe `file_path`/`storage_path`.

Riscos encontrados:

- `app/Modules/Learning/Http/Resources/Course/CourseMaterialResource.php` expõe `file_path` na superfície legacy/administrativa;
- o Resource legacy `EnrollmentResource` inclui `user.tenant_id` quando a relação está carregada;
- `IssueCertificateOnCourseCompletedListener.php:20-26` registra o objeto inteiro da exceção. Isso pode materializar stack/contexto sensível em logs e não segue uma allowlist de erro seguro.

Não reproduzi vazamento desses campos na resposta Student canônica; classifico como concern de superfície legacy/logging, não como leak Student confirmado.

**PII verdict: PII_CONTAINED_ON_CANONICAL, LEGACY_AND_LOGGING_CONCERN.**

## 17. Performance

Feature tests de query bounded passaram para Student My Courses, Student lesson navigation, Instructor roster e Instructor results (`tests/Feature/Api/Learning/StudentS02PerformanceTest.php`, `StudentS01ApiTest.php` e `tests/Feature/Api/Assessment/InstructorResultsPerformanceTest.php`). Não há crescimento linear demonstrado nesses smoke datasets.

Não houve profiling de produção, carga concorrente ou medição de storage URL. A classificação é limitada ao contrato de query dos testes.

**Verdict: PASS — TEST_VERIFIED, sem prova de escala de produção.**

## 18. Scribe/OpenAPI

Não rodei `composer docs`, pois isso alteraria `public/docs/openapi.yaml`, proibido nesta auditoria. A inspeção estática encontrou 186 rotas `api/v1` no route list atual e 121 path entries no OpenAPI estático.

O OpenAPI contém as superfícies Student/Instructor/Admin/MZRT, mas também documenta `/api/v1/assessment/attempts/*` como operação normal e inclui schemas de Course com `certificate_*`, além de schemas/Resources legacy contendo `tenant_id`, `instructor_id`, `file_path`, `provider_ref` e outros internals. Não houve geração atual para provar equivalência exata entre rota e documento.

**Verdict: SCRIBE_OPENAPI_EVIDENCE_WEAK; documentação não pode ser usada como prova de closure atual.**

## 19. Spec/State Drift

Há drift material, não apenas cosmético:

- relatórios anteriores declaram MZRT/Instructor/Student completos apesar do provisioning HTTP atual falhar e do E2E S-02 ser fixture-assisted;
- relatórios falam em 73 migrations, enquanto o filesystem atual tem 65;
- `STUDENT-S02-COMMERCIAL-CLOSURE-2026-09-08.md` diz que nenhum endpoint/fluxo Student Assessment foi iniciado, mas o código mantém Student permissions e endpoints legacy de attempts;
- `docs/STATE.md` estava dirty no snapshot e não foi atualizado nesta auditoria.

Esse drift é capaz de induzir uma nova sessão a tratar uma closure declarativa como `RUNTIME_VERIFIED`.

## 20. Operations Confirmation

A classificação anterior `PAID_PILOT_NEAR_READY` é honesta apenas como descrição de trabalho pendente, não como autorização de release. Os próprios relatórios registram gaps em deploy/migrations operacionais, backup, restore, rollback e error monitoring. Não encontrei evidência atual de um runbook executado para esses itens; workers, storage, secrets e health também não foram demonstrados em uma operação de piloto.

Como a jornada funcional ainda está rejeitada e os mínimos backup/restore/rollback/monitoring permanecem sem prova, não promovo o piloto.

## 21. Assessment/Certificate Constraints

O Course do S-02 desliga `certificate_enabled` e não promete Assessment; esse caso específico pode operar sem quiz/certificado.

O risco está no contrato executável: requests Admin/Instructor aceitam ligar `certificate_enabled` e `certificate_requires_quiz`, e o listener de emissão depende de Assessment. Se o Course exigir quiz sem questionnaire/attempt válido, `IssueCertificateAction` simplesmente retorna sem certificado; exceções do listener são capturadas e logadas. Não há gate de release/configuração que impeça vender ou publicar essa combinação.

**Conclusão:** certificate/Assessment não estão confirmados para venda; a configuração permite criar um Course incompatível com a capacidade Student condicional.

## 22. Regression

Executado no container Laravel:

| Comando | Resultado | Exit |
|---|---:|---:|
| `php artisan test --compact --testsuite=Architecture` | 36 testes, 1.325 assertions | 0 |
| `php artisan test --compact --testsuite=Feature` | 646 testes, 4.141 assertions | 0 |
| `php artisan e2e:run learning/student-s02-commercial-integrated --base=http://localhost:8080 --fresh` | 28 passou, 0 falhou; fixture-assisted | 0 |
| `php artisan e2e:run mzrt/tenant-lifecycle --base=http://localhost:8080 --fresh` | 1 passou, 1 falhou; provisioning 500 | 1 |
| `php artisan e2e:run instructor/i04-consolidated-closure --base=http://localhost:8080 --fresh` | 27 passou, 0 falhou; fixture-assisted | 0 |
| `php artisan migrate:rollback --step=1 --force && php artisan migrate --force` | concluído no DB E2E | 0 |
| `composer validate --strict --no-check-publish` | composer.json válido | 0 |
| `composer check-platform-reqs` | requisitos PHP/extensões satisfeitos | 0 |
| `composer audit --locked --no-interaction` | sem advisories | 0 |

Não rodei formatter, `composer docs`, stage, commit ou push.

## 23. Evidence Levels

| Macroárea | Evidência real atual |
|---|---|
| Core/Tenant/RBAC/route guards | `FEATURE_VERIFIED` + `ARCHITECTURE_VERIFIED` |
| MZRT provisioning/lifecycle | `STATIC_ONLY`; tentativa HTTP falhou |
| Admin | `FEATURE_VERIFIED` + `ARCHITECTURE_VERIFIED` + E2E fixture-assisted |
| Instructor | `FEATURE_VERIFIED` + E2E fixture-assisted |
| Student content/progress | `FEATURE_VERIFIED` + E2E fixture-assisted |
| Assessment Student | `STATIC_ONLY` para a exposição legacy; não é capability fechada |
| Financial side effects comerciais | `FEATURE_VERIFIED` parcial + fixture-assisted E2E |
| Tenant isolation | `FEATURE_VERIFIED`/`ARCHITECTURE_VERIFIED` nos caminhos exercitados |
| Performance | `FEATURE_VERIFIED` por query-count tests |
| Migrations | `RUNTIME_VERIFIED` para fresh e rollback/reapply parcial |
| Scribe/OpenAPI | `STATIC_ONLY` |
| Commercial journey | `E2E_VERIFIED` apenas qualificado; não `RUNTIME_VERIFIED` independente |

## 24. Findings

### BLOCKER

**B-001 — MZRT provisioning HTTP quebra em fresh runtime.** Claim afetada: MZRT complete, fresh install e commercial journey. Evidência: POST de tenant no `mzrt/tenant-lifecycle` retorna 500 `MissingAppKeyException` ao provisionar `TenantPluginConfig`; `.env.e2e.example` não fornece `APP_KEY`. Reprodução: comando MZRT acima, exit 1. Impacto: um ambiente novo não consegue completar o primeiro passo do produto. Invalida completion: sim. Recomendação conceitual: tornar a configuração criptográfica obrigatória e validar o boot antes do receipt; rerodar em app/DB isolados.

**B-002 — S-02 é false success como prova comercial independente.** Claim afetada: Student commercial complete, paid pilot near-ready e commercial journey. Evidência: setup direto de usuários/cursos/matrículas negativas e capture direto de Order/OrderItem/Payment; MZRT só é consultado depois de `bootFixtures`. Reprodução: linhas citadas em `tests/e2e-http/learning/student-s02-commercial-integrated.php`; exit 0 permanece insuficiente. Impacto: a suite pode passar mesmo quando provisioning e criação de efeitos não funcionam por HTTP. Invalida completion: sim.

### HIGH

**H-001 — Legacy Assessment permite operação Student fora da área e sem enrollment.** Claim afetada: Student comercial/Assessment condicional, legacy bypass e cross-persona. Evidência: `Assessment/Routes/api.php:33-41` usa auth + tenant access sem `area.guard:student`; config concede `assessment.attempts.create/answer/finish` a student; `StartAttemptAction.php:21-46` só valida tenant, questionário ativo e perguntas, não matrícula/acesso/publicação do Course parent. Impacto: Student pode iniciar tentativa de questionário ativo sem elegibilidade e por uma superfície que o contrato dizia não estar fechada. Invalida completion: sim para qualquer closure que inclua Assessment; também impede afirmar que Assessment está simplesmente indisponível.

**H-002 — Course pode ser configurado para exigir certificado/quiz sem gate de capacidade.** Claim afetada: Assessment/certificate release constraint e paid pilot. Evidência: `StoreCourseRequest.php:32-35` e `UpdateCourseRequest.php:32-35`; `IssueCertificateAction.php:18-27` retorna sem certificado quando não há passing attempt; listener captura qualquer Throwable em `IssueCertificateOnCourseCompletedListener.php:20-26`. Impacto: Course vendável/publicável pode prometer ou configurar uma condição que Student não consegue cumprir, com falha silenciosa. Invalida completion: sim para oferta com certificate/quiz; não para o Course S-02 explicitamente sem essa promessa.

**H-003 — Mínimos operacionais do piloto continuam sem evidência executada.** Claim afetada: `PAID_PILOT_NEAR_READY`. Evidência: relatórios e inspeção não mostram backup/restore/rollback/error monitoring verificados; o runtime E2E tem configuração de chave incompleta. Impacto: release pago sem recuperação/observabilidade mínima. Invalida completion: sim para `PAID_PILOT_READY`; não é correção de código nesta task.

### MEDIUM

**M-001 — Runner ignora falha de `migrate:fresh`.** `E2eRunCommand.php:80-82` executa a task sem usar o resultado para abortar. Pode mascarar migration failure até um canário/caso posterior e tornar diagnóstico menos determinístico. Invalida completion: não isoladamente; reduz confiança no harness.

**M-002 — Integridade tenant-parent não é majoritariamente uma invariante de banco.** FKs simples permitem combinações de tenant e parent inconsistentes se um caminho de aplicação falhar. Não houve escape reproduzido. Invalida completion: não isoladamente; é risco de defesa em profundidade.

**M-003 — OpenAPI estático não é uma prova atual de contrato.** Há 186 rotas `api/v1` contra 121 paths documentados, e a documentação inclui legacy Assessment e campos internos em schemas. `composer docs` não foi executado por ser mutante. Invalida completion: sim para claim de contrato/documentação fechado; não prova por si só leak HTTP canônico.

**M-004 — Drift de migration/proveniência.** Filesystem tem 65 migrations e timestamp duplicado `2026_07_07_221108`, enquanto relatórios falam em 73. Fresh e rollback parcial passam, mas o inventário publicado está errado. Invalida completion: não isoladamente.

**M-005 — Logging de exceção sem allowlist.** O listener registra `exception` inteiro juntamente com IDs de domínio. Isso pode vazar detalhes de infraestrutura/stack para o audit/log sink. Não foi observado PII concreto no output desta auditoria. Invalida completion: não isoladamente; requer revisão de segurança de logging.

**M-006 — Learning/Assessment legacy sem area guard.** Mesmo onde policy bloqueia a mutação indevida, a mesma capability permanece exposta em superfície domain-first sem o boundary de persona exigido para endpoints novos. Invalida completion: sim para convergência de superfície; não foi classificado como exploit cross-tenant confirmado.

## 25. Area Verdicts

- MZRT: `MZRT_REJECTED`.
- Admin: `ADMIN_EVIDENCE_WEAK`.
- Instructor: `INSTRUCTOR_EVIDENCE_WEAK`.
- Student: `STUDENT_EVIDENCE_WEAK`.
- Commercial Journey: `COMMERCIAL_JOURNEY_REJECTED`.

## 26. Global Product Verdict

**`PRODUCT_CLOSURE_REJECTED`**

B-001 e B-002 impedem aceitar a cadeia comercial como produto demonstrado; H-001/H-002 mostram que a superfície real também diverge das claims condicionais de Assessment/certificate.

## 27. Paid Pilot Verdict

**`PAID_PILOT_NOT_READY`**

Operations mínimas continuam sem prova e a jornada comercial independente não foi confirmada. O rótulo `PAID_PILOT_NEAR_READY` pode permanecer como estado de planejamento, mas não como readiness de release.

## 28. Recommended Remediation Order

1. Corrigir/validar `APP_KEY` e o boot do runtime descartável; rerodar provisioning MZRT por HTTP e verificar rollback transacional em failure.
2. Reescrever o receipt comercial para que tenants, memberships, Course/content, enrollment e order/payment sejam produzidos pela API; deixar setup direto apenas para dados explicitamente fora da jornada e contar efeitos antes/depois.
3. Fechar ou desabilitar a superfície legacy Student Assessment até existir guard Student, parent-access/enrollment e receipt próprio; inventariar todos os equivalentes legacy Learning/Assessment.
4. Impor gate de release para `certificate_*`/Assessment ou provar a jornada Student condicional antes de permitir oferta correspondente.
5. Corrigir o runner para abortar em falha de migration, e repetir a matriz cross-persona/tenant em app e DB realmente alinhados.
6. Regenerar e auditar Scribe/OpenAPI após estabilizar as rotas, removendo internals de schemas públicos e marcando capabilities deferred.
7. Registrar decisão para coerência tenant-parent no banco e limpar o drift de inventário/migration.
8. Só então executar e evidenciar backup/restore, rollback, health, secrets, workers, storage e error monitoring do piloto.
