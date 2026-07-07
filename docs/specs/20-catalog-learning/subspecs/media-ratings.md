---
domain: catalog-learning
parent: ../spec.md
resource: media-ratings
last-reviewed: 2026-07-07
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
- Sistema faz rollup das notas em cache `RatingStats` (média, total, distribuição) e ranking por tenant.

## Endpoints

- `GET /api/v1/learning/lessons/{id}` resolve a mídia ativa da aula e só a expõe quando
  `canAccessLesson()` permitir.
- `POST /api/v1/learning/lessons/{lessonId}/media` cria mídia da aula para instrutor/admin no
  tenant atual; `sort_order` default = fim da fila.
- `PATCH /api/v1/learning/lessons/{lessonId}/media/{mediaId}` atualiza payload da mídia no tenant
  atual.
- `DELETE /api/v1/learning/lessons/{lessonId}/media/{mediaId}` remove a mídia da aula no tenant atual.
- `POST /api/v1/learning/courses/{courseId}/materials` cria o registro base de material extra do
  curso no tenant atual.
- `POST /api/v1/learning/courses/{courseId}/materials/{materialId}/downloads` registra um download
  granular do material para o usuário autenticado no tenant atual e devolve a URL temporária de
  download.

## Permissions

Reaproveitam `learning.lessons.*` (mídia da aula) e `learning.courses.*`. Ratings exigem apenas
autenticação do aluno (own).

## Notes

- `LessonView` e `Rating`/`RatingStats` seguem **pendentes** (ver `../tasks.md`).
- Pre-signed URL de `LessonMedia` segue como próximo delta; materiais já devolvem URL temporária
  no endpoint de download.
