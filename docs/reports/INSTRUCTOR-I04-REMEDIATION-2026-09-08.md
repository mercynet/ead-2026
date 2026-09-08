# I-04 Remediation — Instructor — 2026-09-08

## 1. Scribe finding

O finding documental era real: o extractor padrão do Scribe convertia regras `prohibited` em
parâmetros aceitos. Na geração anterior, os campos que apareciam indevidamente na superfície
Instructor eram `tenant_id`, `status`, `billing_type`, `instructor_id`, `price_cents`, `lesson_id`,
`course_module_id` e `owner` — em requests nas quais esses campos eram proibidos pelo contrato.
Isso não alterava a validação HTTP, mas fazia o contrato publicado aceitar payloads que o runtime
rejeita.

## 2. Scribe fix/evidence

Foi corrigida somente a camada documental:

- `config/scribe.php` substitui as estratégias padrão de body/query derivadas de FormRequest pelas
  estratégias locais seguras;
- as estratégias locais removem, para rotas `api/v1/instructor`, qualquer campo cuja regra contenha
  `prohibited`;
- não houve alteração de regra de negócio, autorização ou payload aceito no runtime.

`composer docs` terminou com exit 0. A inspeção do conteúdo gerado encontrou zero ocorrência de
campo proibido nas operações Instructor e zero hit nos parâmetros proibidos do OpenAPI.

O inventário real/documentado foi comparado após normalizar o alias `GET|HEAD`:

| Evidência | Resultado |
|---|---:|
| Rotas reais Instructor | 49 |
| Entradas Scribe Instructor | 49 |
| Rotas reais sem documentação | 0 |
| Entradas Scribe sem rota real | 0 |
| Operações Instructor OpenAPI com campo proibido | 0 |

Os nomes que ainda aparecem em contextos legítimos não são resíduos do finding: `status` é filtro
GET de roster, `price_cents` pertence à autoria de curso e `course_module_id` é o parent explícito
de criação/reordenação de aula. Eles não estão marcados como `prohibited` nessas requests.
Não há endpoint Instructor para capability deferred documentada como suportada. As respostas de
Resource mantêm a projeção least-privilege já coberta por Feature e E2E; os artefatos Scribe não
geram schema completo de resposta para todas essas Resources, portanto essa ausência não foi usada
como falsa prova de schema.

## 3. Performance finding

O concern de N+1 foi confirmado no path de results. `InstructorResultScope` resolvia o Course de
cada Questionnaire com `courseIdForParent()` dentro de loop e buscava matrículas com
`enrolledUserIdsForCourse()` por Course. O custo crescia com a quantidade de rows/resultados.

## 4. Query evidence

O probe discriminante cria múltiplos students, quatro Courses/Questionnaires/attempts e dois
answer snapshots por attempt. Com o query log limpo entre as medições:

| Dataset | Rows de resultado | Queries do request |
|---|---:|---:|
| pequeno | 1 | 11 |
| maior | 4 | 11 |

O path medido usa um conjunto fixo de consultas para Courses/Lessons próprios, Questionnaires,
resolução batch dos parents, matrículas batch, attempts, `questionnaire`, `user`, `answers` e a
contagem agrupada de attempts. Não houve query por attempt, question ou student e não houve lazy
loading dentro do loop de Resource.

## 5. Performance fix

`AssessmentCatalog` agora oferece resolução batch de parent→Course e Course→enrolled users.
`InstructorResultScope` coleta IDs, resolve os dois conjuntos em lote e monta os pares em memória.
Foram preservados os eager loads mínimos existentes:

- `questionnaire:id,title,type,quizable_id,quizable_type`;
- `user:id,name,avatar`;
- `answers`.

Nenhuma relação extra indiscriminada foi carregada e o filtro de ownership/tenant permaneceu no
mesmo boundary.

O teste discriminante é
`tests/Feature/Api/Assessment/InstructorResultsPerformanceTest.php`; ele exige que o dataset maior
não ultrapasse o dataset menor por mais de duas queries e verifica quatro rows de resultado.

## 6. Regression

Validações finais executadas no container Laravel:

- Feature Instructor relevante: **33 passed, 564 assertions**;
- Assessment Feature regression: **58 passed, 436 assertions**;
- Architecture: **33 passed, 1188 assertions**;
- PHPStan: **506 arquivos analisados, 0 erros**;
- Pint: **pass**;
- `git diff --check`: **pass**;
- `scripts/ai/verify-changes.sh`: **pass**, 10 arquivos de Architecture selecionados;
- Scribe: **exit 0**, seguido de auditoria de conteúdo, não somente do exit code.

E2E HTTP real do path alterado, contra servidor com `APP_ENV=e2e` e banco `ead2026_e2e`:
`instructor/i03-assessment-own-results` — **20 passed, 0 failed**.

## 7. Remaining evidence

Não há finding I-04 remanescente nos dois itens desta remediação. Permanecem fora do escopo, sem
reabertura de I-01/I-02/I-03, as capabilities já declaradas deferred: publish/unpublish,
assignment/reassignment, paid external, MediaProvider, quiz avançado/manual grading, certificates
Instructor, Student, plugins, WS2 e WS3.

O trabalho está no working tree: não houve stage, commit ou push.

## 8. Final verdict

`INSTRUCTOR_COMPLETE`

Base da promoção: Scribe coerente com as 49 rotas reais e sem campos proibidos documentados;
N+1 confirmado e corrigido com query count constante no smoke de volume; Feature, Assessment,
Architecture, PHPStan, Pint, verify-changes, diff check e E2E verdes.
