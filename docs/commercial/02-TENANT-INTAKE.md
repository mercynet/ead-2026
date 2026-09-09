# Pilot tenant intake

Preencher com o cliente antes do provisioning. Não incluir senha, token, documento pessoal ou
secret; contatos devem ser identificadores de trabalho e ficar no sistema apropriado.

```yaml
pilot:
  customer_reference: ""
  pilot_start: "YYYY-MM-DD"
  expected_students: 0
  billing_arrangement: "external|manual|other-human-decision"
company:
  legal_name: ""
  display_name: ""
  slug: ""
  timezone: "America/Sao_Paulo"
  locale: "pt-BR"
  branding:
    logo_reference: ""
    primary_color: ""
admin:
  name: ""
  contact: ""
  invitation_method: "operator-invite|customer-provided"
instructor:
  initial_owner_name: ""
  contact: ""
course:
  title: ""
  description: ""
  commercial_model: "free|manual|cash"
  content_outline: []
students:
  onboarding: "manual|import-after-human-review"
  initial_cohort_reference: ""
commercial:
  support_contact: ""
  agreed_scope_reference: ""
  price_reference: "HUMAN_DECISION_REQUIRED"
```

O domínio atual suporta tenant, usuários, courses, modules, lessons, material/media, enrollment
manual/cash e progress. Não adicionar ao intake campos de Assessment, certificates, PSP, analytics
avançado ou branding que não tenha correspondente confirmado no contrato atual. Se um desses itens
for decisivo, classificar como `BLOCKING_CAPABILITY` e não prometer.
