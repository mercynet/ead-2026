---
domain: catalog-learning
parent: ../spec.md
resource: media-ratings
last-reviewed: 2026-06-10
---

# Media, Materials & Ratings

## Model / Schema

```
lesson_media
- id, lesson_id
- media_type             // Enum MediaType: YouTube | Vimeo | AWS S3 | Live | Audio | Document
- provider_ref           // ID externo (devolvido pelo provider)

lesson_media_progress
- id, user_id, lesson_media_id
- watched_seconds, is_completed

lesson_views                 // ESTATÍSTICA (replay)
- id, user_id, lesson_id, viewed_at

course_materials
- id, course_id, instructor_id
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
- Múltiplos provedores via Enum `MediaType` (YouTube/Vimeo/AWS, Live Streaming, Áudio, PDF).
- Integrações devolvem IDs; a camada Laravel envelopa em Player URL configurável (chaves globais
  ou do tenant, conforme plugin "Private External Storage").

### Reassistir

- Uma aula pode ser assistida várias vezes. `is_completed=true` permanece true após novos acessos.
- Cada acesso gera `lesson_views` (estatística de replay) e emite `LessonViewedEvent`.

### Materiais

- Cada `CourseMaterial` (≤50MB, pasta do tenant) tem tracking granular de downloads
  (`MaterialDownload`), alimentando `MaterialStats`.

### Ratings

- Alunos avaliam cursos e aulas (1-5 estrelas) + like/dislike.
- Sistema faz rollup das notas em cache `RatingStats` (média, total, distribuição) e ranking por tenant.

## Endpoints

Endpoints específicos ainda não definidos/implementados — ver [`../tasks.md`](../tasks.md). Acesso
à mídia ocorre via `GET /lessons/{id}` (resolve pre-signed URL + links temporários de material).

## Permissions

Reaproveitam `learning.lessons.*` (mídia da aula) e `learning.courses.*`. Ratings exigem apenas
autenticação do aluno (own).

## Notes

- `LessonMedia`, `LessonView`, `Rating`/`RatingStats`, `CourseMaterial`/`MaterialDownload` são
  majoritariamente **pendentes** (ver `../tasks.md`).
