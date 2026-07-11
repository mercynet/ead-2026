---
domain: catalog-learning
parent: ../spec.md
resource: media-ratings
last-reviewed: 2026-07-09
---

# Media, Materials & Ratings

## Model / Schema

```
lesson_media
- id, tenant_id, lesson_id
- media_type             // video | audio | document | text | embed (string por enquanto; enum depois)
- provider               // youtube | vimeo | s3 | internal | embed | null
- provider_ref           // ID externo (devolvido pelo provider)
- url                    // URL/player/link temporário ou externo; não proxy binário
- content                // payload textual mínimo para aula text/embed quando aplicável
- duration_seconds
- sort_order, is_active
- metadata               // JSON flexível p/ provider sem vazar segredo

lesson_media_progress
- id, user_id, lesson_media_id
- watched_seconds, is_completed

lesson_views                 // ESTATÍSTICA (replay)
- id, user_id, lesson_id, viewed_at

course_materials
- id, tenant_id, course_id, instructor_id
- file_path              // pasta do tenant, limite 50MB
material_downloads / material_stats   // tracking granular de download

ratings
- id, tenant_id, user_id
- rateable_type, rateable_id   // Course ou Lesson
- stars                  // 1-5
- like / dislike

rating_stats             // cache agregado por curso (média, total, distribuição)
```

## Rules

### Mídia (sem proxy binário)

- Mídias resolvem **pre-signed URLs** apontando para o storage do tenant (AWS S3) ou provider
  externo (Vimeo). Backend nunca faz proxy binário de arquivos grandes. Ver
  [`../../00-architecture/performance-scalability.md`](../../00-architecture/performance-scalability.md).
- Para providers `internal`/`s3`, o caminho lógico do arquivo vive em `metadata.storage_path`
  (e opcionalmente `metadata.storage_disk`); o `GET /lessons/{id}` devolve `url` temporária
  já resolvida + `url_expires_at`.
- Para providers externos, o `GET /lessons/{id}` devolve `url` já consumível pelo cliente e marca
  `url_kind` para remover ambiguidade: `player` (Vimeo/YouTube/embed) ou `direct` quando houver URL
  externa simples. `embed` pode informar `metadata.player_url`; `youtube`/`vimeo` podem resolver a
  player URL canônica a partir de `provider_ref`.
- O contrato de leitura também expõe `provider_config` normalizado sem esconder `metadata`:
  `youtube`/`vimeo` devolvem `video_id` (= `provider_ref`) e opcional `player_url`; `embed`
  devolve `player_url`; `internal`/`s3` devolvem `storage_path` e opcional `storage_disk`.
- `ProgressStrategy::time_based` **não** ganhou coluna dedicada neste slice: o limiar canônico
  continua em `metadata.required_seconds`, e o envelope de leitura o expõe em `progress_config`.
- Múltiplos provedores começam como strings validadas no domínio; enum dedicado entra quando houver
  adapter/provider real.
- Integrações devolvem IDs; a camada Laravel envelopa em Player URL configurável (chaves globais
  ou do tenant, conforme plugin "Private External Storage").
- `GET /api/v1/learning/lessons/{id}` só retorna `media` quando `canAccessLesson()` permitir.
  Aula paga sem matrícula ativa/expirada retorna vitrine da aula com `media=null`.

### Reassistir

- Uma aula pode ser assistida várias vezes. `is_completed=true` permanece true após novos acessos.
- Cada acesso gera `lesson_views` (estatística de replay) e emite `LessonViewedEvent`.

### Materiais

- Cada `CourseMaterial` (≤50MB, pasta do tenant) tem tracking granular de downloads
  (`MaterialDownload`), alimentando `MaterialStats`.
- O registro de download devolve uma **URL temporária** do arquivo (local/S3) para o cliente baixar
  direto do storage, sem proxy binário pelo backend.

### Ratings

- Alunos avaliam cursos e aulas (1-5 estrelas) + like/dislike.
- O slice atual expõe `POST /api/v1/learning/courses/{courseId}/ratings` e `POST /api/v1/learning/lessons/{lessonId}/ratings` apenas para **student** autenticado do tenant atual.
- Curso precisa estar `published` + `is_active=true`; se for pago, exige matrícula ativa para avaliar.
- A avaliação é **own** por `tenant_id + user_id + rateable_type + rateable_id`: novo POST cria; POST subsequente atualiza o mesmo registro.
- O contrato público do endpoint de curso expõe `course_id`, `stars`, `reaction` e bloco `stats`; o detalhe polimórfico fica interno.
- Sistema faz rollup das notas em cache `RatingStats` (média, total, distribuição) e ranking por tenant.

## Endpoints

- `GET /api/v1/learning/lessons/{id}` resolve a mídia ativa da aula e só a expõe quando
  `canAccessLesson()` permitir. Quando o acesso é permitido, o endpoint também grava um
  `lesson_views` por requisição e dispara `LessonViewedEvent`.
- `POST /api/v1/learning/lessons/{lessonId}/media` cria mídia da aula para instrutor/admin no
  tenant atual; `sort_order` default = fim da fila.
- `PATCH /api/v1/learning/lessons/{lessonId}/media/{mediaId}` atualiza payload da mídia no tenant
  atual.
- `DELETE /api/v1/learning/lessons/{lessonId}/media/{mediaId}` remove a mídia da aula no tenant atual.
- `POST /api/v1/learning/courses/{courseId}/materials` cria o registro base de material extra do
  curso no tenant atual. **Hardening de `file_path`** (mesmo padrão do `storage_path` de lesson
  media): obrigatório prefixo `tenants/{tenant_id}/` do tenant atual, sem `..` nem `\` (422).
- `POST /api/v1/learning/courses/{courseId}/materials/{materialId}/downloads` registra um download
  granular do material para o usuário autenticado no tenant atual e devolve a URL temporária de
  download. Defesa em profundidade na geração da URL: allowlist de disk (`local`/`s3`) e
  revalidação do `file_path` persistido contra o tenant do material (charset + prefixo +
  anti-traversal); path inválido → 422 **sem** registrar download.
- `POST /api/v1/learning/courses/{courseId}/ratings` cria/atualiza a avaliação própria do aluno para
  o curso atual e devolve o rating persistido junto das stats agregadas do curso.
- `POST /api/v1/learning/lessons/{lessonId}/ratings` cria/atualiza a avaliação própria do aluno para
  a aula atual e devolve o rating persistido junto das stats agregadas da aula.

## Permissions

Reaproveitam `learning.lessons.*` (mídia da aula) e `learning.courses.*`. Ratings exigem apenas
autenticação do aluno (own).

## Notes

- `Rating`/`RatingStats` de **Course** e **Lesson** estão entregues; falta o delta de ranking por
  tenant (ver `../tasks.md`). O ranking público por enquanto só é exposto no catálogo de cursos
  via `GET /api/v1/learning/catalog/courses?sort=top_rated`; lessons seguem sem superfície
  pública de ranking.
- Pre-signed URL de `LessonMedia` segue como próximo delta; materiais já devolvem URL temporária
  no endpoint de download.
