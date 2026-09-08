# OPS-01 — Evidence checkpoint — 2026-09-09

## Verdict

Este checkpoint confirma a regressão local e o hardening do runner, mas não transforma o
working tree em release. O paid pilot continua `NOT_READY`; backup/restore, deploy, secrets,
TLS, storage, monitoring e rollback permanecem fora da evidência executada.

## Proveniência do snapshot

- Branch: `main`.
- HEAD: `8df531fbc826c79aa4073dfbb70ee7a191ad7cb7`.
- Snapshot dirty antes deste relatório: 123 entradas (`35` modificadas e `88` não rastreadas).
- Nenhum stage, commit ou push foi feito nesta sessão.
- O snapshot inclui trabalho acumulado anterior; este relatório não atribui todos os paths a um
  único slice.

## Evidência executada

| Prova | Resultado |
|---|---|
| `E2eRunCommandTest.php` + `DestructiveDatabaseSafetyTest.php` | 16/16, 36 assertions |
| Regressão Feature (`--testsuite=Feature`) | 668/668, 4.280 assertions, 669s |
| Pint dirty no container | verde |
| PHPStan (`analyse --memory-limit=1G`) no container | verde, sem erros |
| `git diff --check` | verde |
| Scribe (`composer docs`) | exit 0; documentação/API gerada; avisos de `bodyParameters()` existentes |
| `scripts/ai/verify-changes.sh` | verde, invariantes do diff em 11 arquivos de Architecture |
| `migrate:fresh` no banco E2E descartável | 73/73 migrations; receipt anterior preservado nos relatórios MZRT/comercial |
| E2E MZRT + jornada comercial integrada | receipts anteriores verdes; sem resíduos após cleanup |

O caso `--fresh` do runner não executa DDL dentro da transação de `RefreshDatabase`: o sink real
foi extraído para `App\Shared\Database\FreshDatabaseRefresher`, mantendo o comportamento de
produção e permitindo mockar somente a operação destrutiva no Feature test. O guard fail-closed
continua antes de qualquer fixture ou refresh; `--force-db` permanece rejeitado.

## Manifesto de migrations

O manifesto abaixo é a ordenação lexicográfica dos arquivos carregados por
`database/migrations/*.php` e `app/Modules/*/Database/Migrations/*.php`. São 73 arquivos: 8
globais e 65 distribuídos nos módulos. O fingerprint é calculado sobre linhas
`sha256sum <path>` na mesma ordenação:

`e157125a794172e5577ff967f1793057669a7006e9ec728e73f80e99c9c42f45`

```text
01 app/Modules/Assessment/Database/Migrations/2026_02_22_144816_create_questionnaires_table.php
02 app/Modules/Assessment/Database/Migrations/2026_02_22_144831_create_quiz_questions_table.php
03 app/Modules/Assessment/Database/Migrations/2026_02_22_144837_create_quiz_question_categories_table.php
04 app/Modules/Assessment/Database/Migrations/2026_02_22_144838_create_questionnaire_questions_table.php
05 app/Modules/Assessment/Database/Migrations/2026_02_22_144839_create_quiz_attempts_table.php
06 app/Modules/Assessment/Database/Migrations/2026_02_22_144840_create_quiz_attempt_answers_table.php
07 app/Modules/Assessment/Database/Migrations/2026_02_22_144841_create_certificates_table.php
08 app/Modules/Assessment/Database/Migrations/2026_02_22_152525_make_quiz_questions_quizable_nullable.php
09 app/Modules/Assessment/Database/Migrations/2026_07_11_135339_add_questions_snapshot_to_quiz_attempts_table.php
10 app/Modules/Assessment/Database/Migrations/2026_07_11_152213_add_course_id_to_certificates_table.php
11 app/Modules/Assessment/Database/Migrations/2026_07_11_213448_add_unique_tenant_enrollment_to_certificates_table.php
12 app/Modules/Core/Database/Migrations/0001_01_01_000000_create_users_table.php
13 app/Modules/Core/Database/Migrations/2026_02_21_133211_create_tenants_table.php
14 app/Modules/Core/Database/Migrations/2026_02_21_133212_add_identity_fields_to_users_table.php
15 app/Modules/Core/Database/Migrations/2026_02_21_133213_create_tenant_customizations_table.php
16 app/Modules/Core/Database/Migrations/2026_02_21_133214_create_tenant_integrations_table.php
17 app/Modules/Core/Database/Migrations/2026_02_22_194443_add_user_type_to_users_table.php
18 app/Modules/Core/Database/Migrations/2026_02_22_194529_add_tenant_scope_to_roles_table.php
19 app/Modules/Core/Database/Migrations/2026_07_16_120000_create_invitations_table.php
20 app/Modules/Core/Database/Migrations/2026_07_16_130000_tenant_scope_user_unique_constraints.php
21 app/Modules/Core/Database/Migrations/2026_07_16_140000_create_password_resets_table.php
22 app/Modules/Core/Database/Migrations/2026_07_16_150000_tenant_scope_user_global_email_unique.php
23 app/Modules/Core/Database/Migrations/2026_08_03_120000_add_soft_deletes_to_users_table.php
24 app/Modules/Ecosystem/Database/Migrations/2026_07_12_050000_create_plugins_table.php
25 app/Modules/Ecosystem/Database/Migrations/2026_07_12_060000_create_plugin_activations_table.php
26 app/Modules/Ecosystem/Database/Migrations/2026_07_12_070000_create_tenant_plugin_configs_table.php
27 app/Modules/Ecosystem/Database/Migrations/2026_07_29_000000_add_configuration_version_to_tenant_plugin_configs_table.php
28 app/Modules/Ecosystem/Database/Migrations/2026_07_29_090311_create_tenant_plugin_config_revisions_table.php
29 app/Modules/Financial/Database/Migrations/2026_07_08_020000_create_orders_table.php
30 app/Modules/Financial/Database/Migrations/2026_07_08_020100_create_order_items_table.php
31 app/Modules/Financial/Database/Migrations/2026_07_08_020200_create_payments_table.php
32 app/Modules/Financial/Database/Migrations/2026_07_12_080000_create_platform_payment_gateways_table.php
33 app/Modules/Financial/Database/Migrations/2026_07_28_134115_add_authoritative_payment_classification_to_payments_table.php
34 app/Modules/Financial/Database/Migrations/2026_07_28_150000_add_idempotency_key_to_orders_table.php
35 app/Modules/Financial/Database/Migrations/2026_07_29_000100_add_payment_charge_ownership_columns.php
36 app/Modules/Financial/Database/Migrations/2026_07_29_000200_create_order_paid_outbox_table.php
37 app/Modules/Learning/Database/Migrations/2026_02_21_150100_create_categories_table.php
38 app/Modules/Learning/Database/Migrations/2026_02_21_150200_create_courses_table.php
39 app/Modules/Learning/Database/Migrations/2026_02_21_150300_create_course_modules_table.php
40 app/Modules/Learning/Database/Migrations/2026_02_21_150400_create_lessons_table.php
41 app/Modules/Learning/Database/Migrations/2026_02_21_150500_create_enrollments_table.php
42 app/Modules/Learning/Database/Migrations/2026_02_21_150600_create_category_course_table.php
43 app/Modules/Learning/Database/Migrations/2026_02_21_181219_create_lesson_progress_table.php
44 app/Modules/Learning/Database/Migrations/2026_02_21_182000_enrich_enrollments_table.php
45 app/Modules/Learning/Database/Migrations/2026_02_21_182010_enrich_lesson_progress_table.php
46 app/Modules/Learning/Database/Migrations/2026_02_21_182020_enrich_lessons_table.php
47 app/Modules/Learning/Database/Migrations/2026_02_21_182030_enrich_courses_table.php
48 app/Modules/Learning/Database/Migrations/2026_02_21_182040_enrich_categories_table.php
49 app/Modules/Learning/Database/Migrations/2026_02_22_144803_add_certificate_fields_to_courses_table.php
50 app/Modules/Learning/Database/Migrations/2026_07_02_120000_update_enrollments_current_unique_constraint.php
51 app/Modules/Learning/Database/Migrations/2026_07_07_130000_create_lesson_media_table.php
52 app/Modules/Learning/Database/Migrations/2026_07_07_151756_create_course_materials_table.php
53 app/Modules/Learning/Database/Migrations/2026_07_07_170000_create_material_downloads_table.php
54 app/Modules/Learning/Database/Migrations/2026_07_07_180000_create_material_stats_table.php
55 app/Modules/Learning/Database/Migrations/2026_07_07_221108_add_progress_strategy_to_lesson_media_table.php
56 app/Modules/Learning/Database/Migrations/2026_07_07_221108_create_lesson_media_progress_table.php
57 app/Modules/Learning/Database/Migrations/2026_07_08_000000_add_created_by_instructor_id_to_enrollments_table.php
58 app/Modules/Learning/Database/Migrations/2026_07_08_000100_add_billing_type_to_enrollments_table.php
59 app/Modules/Learning/Database/Migrations/2026_07_08_000200_create_ratings_table.php
60 app/Modules/Learning/Database/Migrations/2026_07_08_000210_create_rating_stats_table.php
61 app/Modules/Learning/Database/Migrations/2026_07_08_120000_create_lesson_views_table.php
62 app/Modules/Learning/Database/Migrations/2026_07_29_103207_create_course_price_histories_table.php
63 app/Modules/Learning/Database/Migrations/2026_07_29_192006_add_ordering_and_tenant_integrity_to_category_course_table.php
64 app/Modules/Learning/Database/Migrations/2026_09_06_120000_add_scope_and_hierarchy_fields_to_categories_table.php
65 app/Modules/Learning/Database/Migrations/2026_09_08_120000_add_content_to_lessons_table.php
66 database/migrations/0001_01_01_000001_create_cache_table.php
67 database/migrations/0001_01_01_000002_create_jobs_table.php
68 database/migrations/2026_02_21_135227_create_personal_access_tokens_table.php
69 database/migrations/2026_02_21_140547_create_permission_tables.php
70 database/migrations/2026_02_21_142005_create_activity_log_table.php
71 database/migrations/2026_02_21_142006_add_event_column_to_activity_log_table.php
72 database/migrations/2026_02_21_142007_add_batch_uuid_column_to_activity_log_table.php
73 database/migrations/2026_02_21_142007_create_media_table.php
```

Há dois arquivos com o timestamp `2026_07_07_221108` e dois com `2026_02_21_142007`; a
ordenação lexicográfica e o nome completo são a referência efetiva atual. O fingerprint não é um
SHA de release: muda se qualquer migration mudar e deve ser recalculado junto com o artefato.

## Pendências de release

1. Revisar os 123 paths dirty do snapshot pré-relatório por slice e separar conteúdo aprovado de histórico/artefatos não
   pertencentes à RC.
2. Exigir working tree limpo, referência imutável, backup/restore comprovados e runbook de deploy
   antes de qualquer decisão de paid pilot.
