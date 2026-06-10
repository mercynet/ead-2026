---
domain: assessment
parent: ../spec.md
resource: certificates
last-reviewed: 2026-06-10
---

# Certificates

## Model / Schema

```
certificates
- id
- tenant_id, user_id, enrollment_id, course_id   // FK
- certificate_number     // único: CERT-{ANO}-{HEX}, ex CERT-2026-A1B2C3D4
- issued_at
- status                 // issued | revoked
- created_at, updated_at
```

Config de emissão fica nas colunas de `courses` (domínio Learning):

```
certificate_enabled         // emite certificado?
certificate_min_progress    // % mínima de conclusão
certificate_requires_quiz   // requer quiz aprovado?
certificate_min_score       // % mínima no quiz
```

## Rules

### Emissão automática

Emitido quando:

1. Progresso ≥ `certificate_min_progress`; **e**
2. Se `certificate_requires_quiz`: quiz aprovado com ≥ `certificate_min_score`.

### Verificação pública

`GET /certificates/verify/{certificateNumber}` (sem auth) retorna:

```json
{
  "valid": true,
  "certificate": {
    "certificate_number": "CERT-2026-A1B2C3D4",
    "status": "issued",
    "issued_at": "2026-01-15T10:00:00Z",
    "course_title": "Curso de Laravel",
    "user_name": "João Silva"
  }
}
```

## Endpoints

| Método | Path | Descrição | Permission |
|--------|------|-----------|------------|
| GET | `/api/v1/assessment/certificates` | Listar certificados | `assessment.certificates.list` |
| GET | `/api/v1/assessment/certificates/{id}` | Ver certificado | `assessment.certificates.view` |
| GET | `/api/v1/assessment/certificates/verify/{code}` | Verificar (público) | público |

## Permissions

```
assessment.certificates.{list,view,revoke}
```

Student acessa apenas os próprios (`own`); instructor/admin têm `view`. Ver
[`../../00-architecture/rbac.md`](../../00-architecture/rbac.md) §4 (Assessment).

## Notes

- Geração de PDF do certificado e eventos `CertificateIssuedEvent`/`CertificateRevokedEvent`
  ainda pendentes — ver [`../tasks.md`](../tasks.md).
